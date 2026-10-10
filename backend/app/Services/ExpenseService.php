<?php

namespace App\Services;

use App\Models\DailySession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    use Concerns;

    public function __construct(private DailySessionService $sessions) {}

    public function create(array $data, User $user, array $options = []): Expense
    {
        $data = Validator::make($data, [
            'shop_id' => 'required|integer',
            'expense_category_id' => 'required|integer|exists:expense_categories,id',
            'expense_date' => 'nullable|date',
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'nullable|in:'.implode(',', self::PAYMENT_METHODS),
            'reason' => 'required|string|max:255',
            'local_uuid' => 'nullable|uuid',
            'client_created_at' => 'nullable|date',
        ])->validate();

        if ($existing = $this->findByLocalUuid(Expense::class, $data)) {
            return $existing;
        }
        $shop = $this->authorizeShop($user, (int) $data['shop_id']);
        if (! ExpenseCategory::whereKey($data['expense_category_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['expense_category_id' => __('The selected expense category is inactive.')]);
        }
        $date = $this->businessDate($data, 'expense_date');

        return DB::transaction(function () use ($data, $user, $shop, $date, $options) {
            $session = $this->sessions->sessionFor($shop->id, $date, $options);
            $expense = Expense::create(array_merge([
                'shop_id' => $shop->id,
                'daily_session_id' => $session?->id,
                'expense_category_id' => $data['expense_category_id'],
                'user_id' => $user->id,
                'expense_date' => $date,
                'amount' => Money::round($data['amount']),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reason' => $data['reason'],
                'status' => 'active',
            ], $this->syncAttributes($data)));
            AuditLogger::log('expense.created', $expense);
            if (! empty($options['allow_closed_day'])) {
                $this->sessions->refreshTotalsIfClosed($session?->id);
            }

            return $expense;
        });
    }

    public function void(Expense $expense, string $reason, User $user): Expense
    {
        $this->requirePermission($user, 'void_transactions', __('You are not allowed to void expenses.'));
        $this->authorizeShop($user, $expense->shop_id);
        if ($expense->status === 'voided') {
            $this->fail('already_voided', __('This expense is already voided.'));
        }
        $session = $expense->daily_session_id ? DailySession::find($expense->daily_session_id) : null;
        if ($session && ! $session->isOpen() && ! $user->isSuperAdmin()) {
            $this->fail('day_closed', __('This expense belongs to a closed day. Only a Super Admin can correct it.'));
        }
        $before = $expense->toArray();
        $expense->update(['status' => 'voided', 'voided_by' => $user->id, 'voided_at' => now(), 'void_reason' => $reason]);
        AuditLogger::log('expense.voided', $expense, $before, ['status' => 'voided', 'reason' => $reason]);
        $this->sessions->refreshTotalsIfClosed($expense->daily_session_id);

        return $expense;
    }
}
