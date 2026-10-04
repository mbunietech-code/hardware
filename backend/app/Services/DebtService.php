<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Debt balance = original amount − repayments (Doc 04). */
class DebtService
{
    use Concerns;

    public function __construct(private DailySessionService $sessions) {}

    public function create(array $data, User $user, array $options = []): Debt
    {
        $data = Validator::make($data, [
            'shop_id' => 'required|integer',
            'type' => 'required|in:receivable,payable',
            'customer_id' => 'nullable|integer',
            'customer_local_uuid' => 'nullable|uuid',
            'supplier_id' => 'nullable|integer',
            'supplier_local_uuid' => 'nullable|uuid',
            'party_name' => 'nullable|string|max:255',
            'party_phone' => 'nullable|string|max:30',
            'amount' => 'required|numeric|gt:0',
            'debt_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ])->validate();

        if ($existing = $this->findByLocalUuid(Debt::class, $data)) {
            return $existing;
        }
        $shop = $this->authorizeShop($user, (int) $data['shop_id']);
        $date = $this->businessDate($data, 'debt_date');
        $customerId = $this->resolveRef(Customer::class, $data, 'customer_id', 'customer_local_uuid', 'customer');
        $supplierId = $this->resolveRef(Supplier::class, $data, 'supplier_id', 'supplier_local_uuid', 'supplier');
        $party = $customerId ? Customer::find($customerId) : ($supplierId ? Supplier::find($supplierId) : null);
        $partyName = $data['party_name'] ?? $party?->name;
        if (! $partyName) {
            throw ValidationException::withMessages(['party_name' => __('Enter who owes or is owed (party name).')]);
        }

        return DB::transaction(function () use ($data, $user, $shop, $date, $options, $customerId, $supplierId, $party, $partyName) {
            $session = $this->sessions->sessionFor($shop->id, $date, $options);
            $amount = Money::round($data['amount']);
            $debt = Debt::create(array_merge([
                'shop_id' => $shop->id,
                'daily_session_id' => $session?->id,
                'user_id' => $user->id,
                'type' => $data['type'],
                'customer_id' => $customerId,
                'supplier_id' => $supplierId,
                'party_name' => $partyName,
                'party_phone' => $data['party_phone'] ?? $party?->phone,
                'original_amount' => $amount,
                'paid_amount' => 0,
                'balance' => $amount,
                'debt_date' => $date,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
            ], $this->syncAttributes($data)));
            AuditLogger::log('debt.created', $debt);

            return $debt;
        });
    }

    public function pay(array $data, User $user, array $options = []): DebtPayment
    {
        $data = Validator::make($data, [
            'debt_id' => 'nullable|integer',
            'debt_local_uuid' => 'nullable|uuid',
            'amount' => 'required|numeric|gt:0',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|in:cash,mobile_money,bank,card',
            'notes' => 'nullable|string|max:255',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ])->validate();

        if ($existing = $this->findByLocalUuid(DebtPayment::class, $data)) {
            return $existing;
        }
        $debtId = $this->resolveRef(Debt::class, $data, 'debt_id', 'debt_local_uuid', 'debt')
            ?? throw ValidationException::withMessages(['debt_id' => __('Select a debt.')]);

        return DB::transaction(function () use ($data, $user, $options, $debtId) {
            $debt = Debt::lockForUpdate()->find($debtId);
            $this->authorizeShop($user, $debt->shop_id);
            if (in_array($debt->status, ['paid', 'cancelled'], true)) {
                $this->fail('debt_closed', __('This debt is already :status.', ['status' => __($debt->status)]), true, ['debt_id' => $debt->id]);
            }
            $amount = Money::round($data['amount']);
            if ($amount > (float) $debt->balance) {
                $this->fail('overpayment', __('Payment of :amount exceeds the outstanding balance of :balance.', ['amount' => Money::format($amount), 'balance' => Money::format($debt->balance)]),
                    true, ['balance' => (float) $debt->balance]);
            }
            $date = $this->businessDate($data, 'payment_date');
            $session = $this->sessions->sessionFor($debt->shop_id, $date, $options);

            $before = $debt->toArray();
            $payment = DebtPayment::create(array_merge([
                'debt_id' => $debt->id,
                'shop_id' => $debt->shop_id,
                'daily_session_id' => $session?->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_date' => $date,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? null,
            ], $this->syncAttributes($data)));

            $paid = Money::round((float) $debt->paid_amount + $amount);
            $balance = Money::round((float) $debt->original_amount - $paid);
            $debt->update(['paid_amount' => $paid, 'balance' => $balance, 'status' => $balance <= 0 ? 'paid' : 'partial']);
            $this->syncSourceBalance($debt);
            if ($balance <= 0) {
                NotificationService::resolve('debt_overdue:'.$debt->id);
                NotificationService::resolve('debt_due:'.$debt->id);
            }
            AuditLogger::log('debt.payment', $payment, ['balance' => (float) $before['balance']], ['balance' => $balance, 'amount' => $amount], $debt->shop_id);

            return $payment->load('debt');
        });
    }

    public function cancel(Debt $debt, string $reason, User $user): Debt
    {
        if (! $user->isSuperAdmin()) {
            throw new AuthorizationException(__('Only a Super Admin can cancel debts.'));
        }
        $before = $debt->toArray();
        $debt->update(['status' => 'cancelled', 'notes' => trim(($debt->notes ?? '')."\nCancelled: {$reason}")]);
        AuditLogger::log('debt.cancelled', $debt, $before, ['status' => 'cancelled', 'reason' => $reason]);

        return $debt;
    }

    /** Keep the originating credit sale/purchase payment status in step with its debt. */
    private function syncSourceBalance(Debt $debt): void
    {
        $source = $debt->sourceRecord;
        if (! $source) {
            return;
        }
        $paid = Money::round((float) $source->total - (float) $debt->balance);
        $source->update([
            'amount_paid' => $paid,
            'balance' => $debt->balance,
            'payment_status' => $debt->balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
        ]);
    }
}
