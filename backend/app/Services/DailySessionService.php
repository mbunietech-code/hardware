<?php

namespace App\Services;

use App\Models\CapitalEntry;
use App\Models\DailySession;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DailySessionService
{
    use Concerns;

    public function open(array $data, User $user): DailySession
    {
        $data = Validator::make($data, [
            'shop_id' => 'required|integer',
            'business_date' => 'nullable|date',
            'opening_cash' => 'nullable|numeric|min:0',
            'opening_notes' => 'nullable|string|max:1000',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ])->validate();

        if ($existing = $this->findByLocalUuid(DailySession::class, $data)) {
            return $existing;
        }
        $shop = $this->authorizeShop($user, (int) $data['shop_id']);
        $date = $this->businessDate($data, 'business_date');

        return DB::transaction(function () use ($data, $user, $shop, $date) {
            $session = DailySession::where('shop_id', $shop->id)->whereDate('business_date', $date)->lockForUpdate()->first();
            if ($session) {
                if (! $session->isOpen()) {
                    $this->fail('day_closed', __('The business day :date for :shop is already closed.', ['date' => $date, 'shop' => $shop->name]), true,
                        ['daily_session_id' => $session->id]);
                }

                // Already opened elsewhere (web or another device): reuse it so the day has one session.
                return $session;
            }

            $session = DailySession::create(array_merge([
                'shop_id' => $shop->id,
                'business_date' => $date,
                'status' => 'open',
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_cash' => Money::round($data['opening_cash'] ?? 0),
                'opening_notes' => $data['opening_notes'] ?? null,
            ], $this->syncAttributes($data)));

            AuditLogger::log('daily_session.opened', $session);

            return $session;
        });
    }

    /**
     * Find the session a transaction on $date belongs to. Throws when the day must
     * be open and is not, or when the day is already closed (unless overridden).
     */
    public function sessionFor(int $shopId, string $date, array $options = []): ?DailySession
    {
        $session = DailySession::where('shop_id', $shopId)->whereDate('business_date', $date)->first();

        if ($session && ! $session->isOpen() && empty($options['allow_closed_day'])) {
            $this->fail('day_closed', __('The business day :date is closed. Ask a Super Admin to reopen it or resolve the conflict.', ['date' => $date]), true,
                ['daily_session_id' => $session->id, 'business_date' => $date]);
        }
        if (! $session && Settings::get('require_open_day') && empty($options['allow_closed_day'])) {
            $this->fail('no_open_day', __('Open the business day for :date before recording transactions.', ['date' => $date]));
        }

        return $session;
    }

    /** Recalculate totals for a session after a late change (e.g. a resolved sync conflict). */
    public function refreshTotalsIfClosed(?int $sessionId): void
    {
        if (! $sessionId) {
            return;
        }
        $session = DailySession::find($sessionId);
        if ($session && ! $session->isOpen()) {
            $before = $session->totals;
            $totals = $this->computeTotals($session);
            $session->update([
                'totals' => $totals,
                'expected_cash' => $totals['expected_cash'],
                'cash_difference' => $session->closing_cash !== null ? Money::round($session->closing_cash - $totals['expected_cash']) : null,
            ]);
            AuditLogger::log('daily_session.totals_recomputed', $session, $before, $totals);
        }
    }

    public function computeTotals(DailySession $session): array
    {
        $sid = $session->id;
        $sales = Sale::where('daily_session_id', $sid)->where('status', 'completed');
        $purchases = Purchase::where('daily_session_id', $sid)->where('status', 'completed');
        $expenses = Expense::where('daily_session_id', $sid)->where('status', 'active');
        $capital = CapitalEntry::where('daily_session_id', $sid)->where('status', 'active');
        $payments = DebtPayment::where('debt_payments.daily_session_id', $sid)->join('debts', 'debts.id', '=', 'debt_payments.debt_id');

        $salesByMethod = (clone $sales)->selectRaw('payment_method, SUM(total) as total, SUM(amount_paid) as paid')
            ->groupBy('payment_method')->get()->keyBy('payment_method');
        $cashSalesPaid = (float) ($salesByMethod['cash']->paid ?? 0);

        $t = [
            'sales_count' => (clone $sales)->count(),
            'sales_total' => Money::round((clone $sales)->sum('total')),
            'sales_paid' => Money::round((clone $sales)->sum('amount_paid')),
            'sales_credit' => Money::round((clone $sales)->sum('balance')),
            'sales_discount' => Money::round((clone $sales)->sum('discount')),
            'sales_by_method' => $salesByMethod->map(fn ($r) => ['total' => Money::round($r->total), 'paid' => Money::round($r->paid)])->all(),
            'cost_of_goods' => Money::round((clone $sales)->sum('cost_total')),
            'purchases_count' => (clone $purchases)->count(),
            'purchases_total' => Money::round((clone $purchases)->sum('total')),
            'purchases_paid' => Money::round((clone $purchases)->sum('amount_paid')),
            'expenses_count' => (clone $expenses)->count(),
            'expenses_total' => Money::round((clone $expenses)->sum('amount')),
            'capital_in' => Money::round((clone $capital)->where('type', 'injection')->sum('amount')),
            'capital_out' => Money::round((clone $capital)->where('type', 'withdrawal')->sum('amount')),
            'debt_payments_received' => Money::round((clone $payments)->where('debts.type', 'receivable')->sum('debt_payments.amount')),
            'debt_payments_made' => Money::round((clone $payments)->where('debts.type', 'payable')->sum('debt_payments.amount')),
            'new_debts_receivable' => Money::round(Debt::where('daily_session_id', $sid)->where('type', 'receivable')->where('status', '!=', 'cancelled')->sum('original_amount')),
            'new_debts_payable' => Money::round(Debt::where('daily_session_id', $sid)->where('type', 'payable')->where('status', '!=', 'cancelled')->sum('original_amount')),
        ];

        // Expected cash in the drawer counts only cash-method money movements.
        $cash = fn ($q, $col) => (float) (clone $q)->where('payment_method', 'cash')->sum($col);
        $t['expected_cash'] = Money::round(
            (float) $session->opening_cash
            + $cashSalesPaid
            + (float) (clone $payments)->where('debts.type', 'receivable')->where('debt_payments.payment_method', 'cash')->sum('debt_payments.amount')
            - (float) (clone $payments)->where('debts.type', 'payable')->where('debt_payments.payment_method', 'cash')->sum('debt_payments.amount')
            - $cash($purchases, 'amount_paid')
            - $cash($expenses, 'amount')
            + (float) (clone $capital)->where('type', 'injection')->where('payment_method', 'cash')->sum('amount')
            - (float) (clone $capital)->where('type', 'withdrawal')->where('payment_method', 'cash')->sum('amount')
        );

        return $t;
    }

    public function close(DailySession $session, array $data, User $user, array $options = []): DailySession
    {
        $data = Validator::make($data, [
            'closing_cash' => 'nullable|numeric|min:0',
            'closing_notes' => 'nullable|string|max:2000',
            'exceptions' => 'nullable|string|max:2000',
            'client_totals' => 'nullable|array',
        ])->validate();

        $this->authorizeShop($user, $session->shop_id);

        return DB::transaction(function () use ($session, $data, $user) {
            $session = DailySession::lockForUpdate()->find($session->id);
            if (! $session->isOpen()) {
                $this->fail('day_closed', __('This business day is already closed.'), true, ['daily_session_id' => $session->id]);
            }

            $totals = $this->computeTotals($session);
            $closingCash = isset($data['closing_cash']) ? Money::round($data['closing_cash']) : null;
            $session->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
                'totals' => $totals,
                'client_totals' => $data['client_totals'] ?? null,
                'expected_cash' => $totals['expected_cash'],
                'closing_cash' => $closingCash,
                'cash_difference' => $closingCash !== null ? Money::round($closingCash - $totals['expected_cash']) : null,
                'closing_notes' => $data['closing_notes'] ?? null,
                'exceptions' => $data['exceptions'] ?? null,
            ]);
            NotificationService::resolve('closing_reminder:'.$session->id);
            NotificationService::resolve('closing_overdue:'.$session->id);
            AuditLogger::log('daily_session.closed', $session, null, $session->toArray());

            return $session;
        });
    }

    /** Super Admin correction path: reopen a closed day; history stays in the audit trail. */
    public function reopen(DailySession $session, string $reason, User $user): DailySession
    {
        if (! $user->isSuperAdmin()) {
            throw new AuthorizationException(__('Only a Super Admin can reopen a closed day.'));
        }
        if ($session->isOpen()) {
            $this->fail('already_open', __('This business day is already open.'));
        }
        $before = $session->toArray();
        $session->update(['status' => 'open', 'reopen_count' => $session->reopen_count + 1]);
        AuditLogger::log('daily_session.reopened', $session, $before, ['status' => 'open', 'reason' => $reason]);

        return $session;
    }
}
