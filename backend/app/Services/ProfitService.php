<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ProfitAllocation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

/**
 * Profit uses the owner-configurable formula and costing method (OD-001/OD-002).
 * Until `profit_rules_approved` is set, results are labelled provisional.
 */
class ProfitService
{
    use Concerns;

    public function summary(string $from, string $to, ?int $shopId = null, ?array $shopIds = null): array
    {
        $sales = Sale::where('status', 'completed')->whereBetween('sale_date', [$from, $to])
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->when($shopIds !== null, fn ($q) => $q->whereIn('shop_id', $shopIds));
        $expenses = Expense::where('expenses.status', 'active')->whereBetween('expense_date', [$from, $to])
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->when($shopIds !== null, fn ($q) => $q->whereIn('shop_id', $shopIds))
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');

        // Customer returns reduce revenue; restocked goods also leave the cost of goods sold.
        $returns = SaleReturn::whereBetween('return_date', [$from, $to])
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->when($shopIds !== null, fn ($q) => $q->whereIn('shop_id', $shopIds));
        $returnsValue = Money::round((clone $returns)->sum('return_value'));
        $revenue = Money::round((clone $sales)->sum('total') - $returnsValue);
        $cogs = Money::round((clone $sales)->sum('cost_total') - (clone $returns)->sum('cost_restocked'));
        $gross = Money::round($revenue - $cogs);
        $expensesTotal = Money::round((clone $expenses)->sum('expenses.amount'));
        $expensesReducing = Money::round((clone $expenses)->where('expense_categories.reduces_profit', true)->sum('expenses.amount'));
        $net = Money::round($gross - $expensesReducing);
        $formula = Settings::get('profit_formula');

        return [
            'period_start' => $from,
            'period_end' => $to,
            'revenue' => $revenue,
            'discounts' => Money::round((clone $sales)->sum('discount')),
            'returns' => $returnsValue,
            'cost_of_goods' => $cogs,
            'gross_profit' => $gross,
            'expenses_total' => $expensesTotal,
            'expenses_reducing_profit' => $expensesReducing,
            'net_profit' => $net,
            'profit' => $formula === 'gross' ? $gross : $net,
            'formula' => $formula,
            'costing_method' => Settings::get('costing_method'),
            'approved' => (bool) Settings::get('profit_rules_approved'),
        ];
    }

    public function generateAllocation(array $data, User $user): ProfitAllocation
    {
        if (! $user->isSuperAdmin()) {
            throw new AuthorizationException(__('Only a Super Admin can generate allocations.'));
        }
        $data = Validator::make($data, [
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'notes' => 'nullable|string|max:1000',
        ])->validate();

        $overlap = ProfitAllocation::where('status', 'approved')
            ->where('period_start', '<=', $data['period_end'])->where('period_end', '>=', $data['period_start'])
            ->where(fn ($q) => empty($data['shop_id']) ? $q : $q->whereNull('shop_id')->orWhere('shop_id', $data['shop_id']))
            ->exists();
        if ($overlap) {
            $this->fail('allocation_overlap', __('An approved allocation already covers part of this period.'));
        }

        $s = $this->summary($data['period_start'], $data['period_end'], $data['shop_id'] ?? null);
        [$p1, $p2] = Settings::allocationPercents();
        $profit = max(0, $s['profit']);
        $primary = Money::round($profit * $p1 / 100);

        $allocation = ProfitAllocation::create([
            'shop_id' => $data['shop_id'] ?? null,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'revenue' => $s['revenue'],
            'cost_of_goods' => $s['cost_of_goods'],
            'expenses' => $s['expenses_reducing_profit'],
            'profit_amount' => $s['profit'],
            'primary_percent' => $p1,
            'secondary_percent' => $p2,
            'primary_amount' => $primary,
            'secondary_amount' => Money::round($profit - $primary),
            'primary_label' => Settings::get('allocation_primary_label'),
            'secondary_label' => Settings::get('allocation_secondary_label'),
            'formula_config' => [
                'profit_formula' => $s['formula'],
                'costing_method' => $s['costing_method'],
                'rules_approved' => $s['approved'],
                'timing' => Settings::get('allocation_timing'),
                'gross_profit' => $s['gross_profit'],
                'net_profit' => $s['net_profit'],
            ],
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
        AuditLogger::log('allocation.generated', $allocation);

        return $allocation;
    }

    public function setAllocationStatus(ProfitAllocation $allocation, string $status, User $user): ProfitAllocation
    {
        if (! $user->isSuperAdmin()) {
            throw new AuthorizationException(__('Only a Super Admin can approve allocations.'));
        }
        if ($allocation->status !== 'draft') {
            $this->fail('allocation_locked', __('Only draft allocations can be approved or cancelled.'));
        }
        $before = $allocation->toArray();
        $allocation->update($status === 'approved'
            ? ['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]
            : ['status' => 'cancelled']);
        AuditLogger::log('allocation.'.$status, $allocation, $before, $allocation->toArray());

        return $allocation;
    }
}
