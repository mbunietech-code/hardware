<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CapitalEntry;
use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ProfitAllocation;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report definitions (Doc 16). Each returns a uniform structure:
 * title, columns, rows, totals, money (columns to format), notice.
 */
class ReportService
{
    public const TYPES = [
        'sales' => 'Sales',
        'purchases' => 'Purchases',
        'stock' => 'Stock balances',
        'stock_movements' => 'Stock movements',
        'expenses' => 'Expenses',
        'debts' => 'Debts',
        'capital' => 'Capital',
        'daily_closing' => 'Daily closing',
        'profit' => 'Profit',
        'allocations' => '60/40 allocation',
        'audit' => 'Audit trail',
    ];

    public const LIMIT = 2000;

    public function __construct(private ProfitService $profit) {}

    public function run(string $type, array $f, User $user): array
    {
        if (! isset(self::TYPES[$type])) {
            abort(404);
        }
        if ($user->isShopAdmin() && ! $user->hasPermission('view_reports')) {
            throw new AuthorizationException(__('You are not allowed to view reports.'));
        }
        if ($type === 'audit' && $user->isShopAdmin() && ! $user->hasPermission('view_audit')) {
            throw new AuthorizationException(__('You are not allowed to view the audit trail.'));
        }
        if ($type === 'allocations' && ! $user->isSuperAdmin()) {
            throw new AuthorizationException(__('Only a Super Admin can view allocations.'));
        }

        $f['from'] = Carbon::parse($f['from'] ?? now()->startOfMonth())->toDateString();
        $f['to'] = Carbon::parse($f['to'] ?? now())->toDateString();
        // Shop Admins are always limited to their own shop (BR-002).
        if ($user->isShopAdmin()) {
            $f['shop_id'] = $user->shop_id;
        }

        $report = $this->{'report'.str_replace('_', '', ucwords($type, '_'))}($f);

        return $report + ['type' => $type, 'filters' => $f, 'notice' => $report['notice'] ?? null];
    }

    private function shop(Builder $q, array $f, string $col = 'shop_id'): Builder
    {
        return $q->when(! empty($f['shop_id']), fn ($q) => $q->where($col, $f['shop_id']));
    }

    private function dates(Builder $q, array $f, string $col): Builder
    {
        return $q->whereDate($col, '>=', $f['from'])->whereDate($col, '<=', $f['to']);
    }

    private function reportSales(array $f): array
    {
        $q = Sale::with('shop:id,name', 'user:id,name', 'customer:id,name');
        $this->shop($this->dates($q, $f, 'sale_date'), $f)
            ->when(! empty($f['user_id']), fn ($q) => $q->where('user_id', $f['user_id']))
            ->when(! empty($f['payment_status']), fn ($q) => $q->where('payment_status', $f['payment_status']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']), fn ($q) => $q->where('status', 'completed'))
            ->when(! empty($f['product_id']), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_id', $f['product_id'])));
        $rows = $q->orderByDesc('sale_date')->orderByDesc('id')->limit(self::LIMIT)->get()->map(fn (Sale $s) => [
            'reference' => $s->reference, 'date' => $s->sale_date->toDateString(), 'shop' => $s->shop->name,
            'user' => $s->user->name, 'customer' => $s->customer?->name ?? __('Walk-in'), 'method' => __(str_replace('_', ' ', $s->payment_method)),
            'status' => __($s->payment_status).($s->status === 'voided' ? ' ('.__('voided').')' : ''),
            'total' => (float) $s->total, 'paid' => (float) $s->amount_paid, 'balance' => (float) $s->balance,
        ]);

        return [
            'title' => 'Sales report',
            'columns' => ['reference' => 'Reference', 'date' => 'Date', 'shop' => 'Shop', 'user' => 'User', 'customer' => 'Customer', 'method' => 'Method', 'status' => 'Payment', 'total' => 'Total', 'paid' => 'Paid', 'balance' => 'Balance'],
            'rows' => $rows,
            'totals' => ['total' => $rows->sum('total'), 'paid' => $rows->sum('paid'), 'balance' => $rows->sum('balance')],
            'money' => ['total', 'paid', 'balance'],
        ];
    }

    private function reportPurchases(array $f): array
    {
        $q = Purchase::with('shop:id,name', 'user:id,name', 'supplier:id,name');
        $this->shop($this->dates($q, $f, 'purchase_date'), $f)
            ->when(! empty($f['supplier_id']), fn ($q) => $q->where('supplier_id', $f['supplier_id']))
            ->when(! empty($f['payment_status']), fn ($q) => $q->where('payment_status', $f['payment_status']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']), fn ($q) => $q->where('status', 'completed'))
            ->when(! empty($f['product_id']), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_id', $f['product_id'])));
        $rows = $q->orderByDesc('purchase_date')->orderByDesc('id')->limit(self::LIMIT)->get()->map(fn (Purchase $p) => [
            'reference' => $p->reference, 'date' => $p->purchase_date->toDateString(), 'shop' => $p->shop->name,
            'supplier' => $p->supplier?->name ?? '—', 'invoice' => $p->invoice_number ?? '—', 'user' => $p->user->name,
            'status' => __($p->payment_status).($p->status === 'voided' ? ' ('.__('voided').')' : ''),
            'total' => (float) $p->total, 'paid' => (float) $p->amount_paid, 'balance' => (float) $p->balance,
        ]);

        return [
            'title' => 'Purchases report',
            'columns' => ['reference' => 'Reference', 'date' => 'Date', 'shop' => 'Shop', 'supplier' => 'Supplier', 'invoice' => 'Invoice', 'user' => 'User', 'status' => 'Payment', 'total' => 'Total', 'paid' => 'Paid', 'balance' => 'Balance'],
            'rows' => $rows,
            'totals' => ['total' => $rows->sum('total'), 'paid' => $rows->sum('paid'), 'balance' => $rows->sum('balance')],
            'money' => ['total', 'paid', 'balance'],
        ];
    }

    private function reportStock(array $f): array
    {
        $q = StockBalance::with('shop:id,name', 'product.category')->whereHas('product', function ($p) use ($f) {
            $p->when(! empty($f['category_id']), fn ($q) => $q->where('category_id', $f['category_id']))
                ->when(! empty($f['product_id']), fn ($q) => $q->where('id', $f['product_id']));
        });
        $this->shop($q, $f);
        $rows = $q->limit(self::LIMIT * 5)->get()
            ->map(fn (StockBalance $b) => [
                'code' => $b->product->code, 'product' => $b->product->name, 'category' => $b->product->category?->name ?? '—',
                'shop' => $b->shop->name, 'unit' => $b->product->unit, 'quantity' => (float) $b->quantity,
                'reorder' => (float) $b->product->reorder_level,
                'state' => (float) $b->quantity <= 0 ? __('Out of stock') : ((float) $b->quantity <= (float) $b->product->reorder_level ? __('Low') : __('OK')),
                'avg_cost' => (float) ($b->avg_cost > 0 ? $b->avg_cost : $b->product->cost_price),
                'value' => Money::round((float) $b->quantity * (float) ($b->avg_cost > 0 ? $b->avg_cost : $b->product->cost_price)),
            ])
            ->when(! empty($f['low_only']), fn ($c) => $c->filter(fn ($r) => $r['state'] !== __('OK')))
            ->sortBy('product')->values();

        return [
            'title' => 'Stock report',
            'columns' => ['code' => 'Code', 'product' => 'Product', 'category' => 'Category', 'shop' => 'Shop', 'unit' => 'Unit', 'quantity' => 'Qty', 'reorder' => 'Reorder level', 'state' => 'Status', 'avg_cost' => 'Unit cost', 'value' => 'Stock value'],
            'rows' => $rows,
            'totals' => ['value' => $rows->sum('value')],
            'money' => ['avg_cost', 'value'],
            'qty' => ['quantity', 'reorder'],
            'no_dates' => true,
        ];
    }

    private function reportStockMovements(array $f): array
    {
        $q = StockMovement::with('shop:id,name', 'product:id,name,code', 'user:id,name');
        $this->shop($this->dates($q, $f, 'created_at'), $f)
            ->when(! empty($f['product_id']), fn ($q) => $q->where('product_id', $f['product_id']))
            ->when(! empty($f['movement_type']), fn ($q) => $q->where('type', $f['movement_type']));
        $rows = $q->orderByDesc('id')->limit(self::LIMIT)->get()->map(fn (StockMovement $m) => [
            'time' => $m->created_at->format('Y-m-d H:i'), 'shop' => $m->shop->name, 'product' => $m->product->name,
            'type' => __(StockMovement::TYPES[$m->type] ?? $m->type), 'quantity' => (float) $m->quantity,
            'before' => (float) $m->before_qty, 'after' => (float) $m->after_qty, 'user' => $m->user?->name ?? __('system'),
            'reason' => $m->reason ?? '',
        ]);

        return [
            'title' => 'Stock movement report',
            'columns' => ['time' => 'Time', 'shop' => 'Shop', 'product' => 'Product', 'type' => 'Type', 'quantity' => 'Change', 'before' => 'Before', 'after' => 'After', 'user' => 'User', 'reason' => 'Reason'],
            'rows' => $rows, 'totals' => [], 'money' => [], 'qty' => ['quantity', 'before', 'after'],
        ];
    }

    private function reportExpenses(array $f): array
    {
        $q = Expense::with('shop:id,name', 'user:id,name', 'category:id,name,reduces_profit');
        $this->shop($this->dates($q, $f, 'expense_date'), $f)
            ->when(! empty($f['category_id']), fn ($q) => $q->where('expense_category_id', $f['category_id']))
            ->when(! empty($f['user_id']), fn ($q) => $q->where('user_id', $f['user_id']))
            ->where('status', $f['status'] ?? 'active');
        $rows = $q->orderByDesc('expense_date')->limit(self::LIMIT)->get()->map(fn (Expense $e) => [
            'date' => $e->expense_date->toDateString(), 'shop' => $e->shop->name, 'category' => $e->category->name,
            'reduces_profit' => $e->category->reduces_profit ? __('Yes') : __('No'), 'reason' => $e->reason,
            'method' => __(str_replace('_', ' ', $e->payment_method)), 'user' => $e->user->name, 'amount' => (float) $e->amount,
        ]);

        return [
            'title' => 'Expense report',
            'columns' => ['date' => 'Date', 'shop' => 'Shop', 'category' => 'Category', 'reduces_profit' => 'Reduces profit', 'reason' => 'Reason', 'method' => 'Method', 'user' => 'User', 'amount' => 'Amount'],
            'rows' => $rows, 'totals' => ['amount' => $rows->sum('amount')], 'money' => ['amount'],
        ];
    }

    private function reportDebts(array $f): array
    {
        $q = Debt::with('shop:id,name');
        $this->shop($q, $f)
            ->when(! empty($f['debt_type']), fn ($q) => $q->where('type', $f['debt_type']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']), fn ($q) => $q->whereIn('status', ['open', 'partial']))
            ->when(! empty($f['party']), fn ($q) => $q->where('party_name', 'like', '%'.$f['party'].'%'))
            ->when(! empty($f['overdue_only']), fn ($q) => $q->whereNotNull('due_date')->where('due_date', '<', now()->toDateString()));
        $rows = $q->orderBy('due_date')->limit(self::LIMIT)->get()->map(fn (Debt $d) => [
            'date' => $d->debt_date->toDateString(), 'party' => $d->party_name, 'phone' => $d->party_phone ?? '',
            'type' => __(ucfirst($d->type)), 'shop' => $d->shop->name, 'due' => $d->due_date?->toDateString() ?? '—',
            'status' => __($d->status).($d->isOverdue() ? ' ('.__('overdue').')' : ''),
            'original' => (float) $d->original_amount, 'paid' => (float) $d->paid_amount, 'balance' => (float) $d->balance,
        ]);

        return [
            'title' => 'Debt report',
            'columns' => ['date' => 'Date', 'party' => 'Party', 'phone' => 'Phone', 'type' => 'Type', 'shop' => 'Shop', 'due' => 'Due', 'status' => 'Status', 'original' => 'Original', 'paid' => 'Paid', 'balance' => 'Balance'],
            'rows' => $rows,
            'totals' => ['original' => $rows->sum('original'), 'paid' => $rows->sum('paid'), 'balance' => $rows->sum('balance')],
            'money' => ['original', 'paid', 'balance'],
            'no_dates' => true,
        ];
    }

    private function reportCapital(array $f): array
    {
        $q = CapitalEntry::with('shop:id,name', 'user:id,name')->where('status', 'active');
        $this->shop($this->dates($q, $f, 'entry_date'), $f)
            ->when(! empty($f['capital_type']), fn ($q) => $q->where('type', $f['capital_type']))
            ->when(! empty($f['user_id']), fn ($q) => $q->where('user_id', $f['user_id']));
        $opening = CapitalEntry::where('status', 'active')->where('entry_date', '<', $f['from'])
            ->when(! empty($f['shop_id']), fn ($q) => $q->where('shop_id', $f['shop_id']))
            ->selectRaw("COALESCE(SUM(CASE WHEN type='injection' THEN amount ELSE -amount END),0) as bal")->value('bal');
        $rows = $q->orderBy('entry_date')->limit(self::LIMIT)->get()->map(fn (CapitalEntry $c) => [
            'date' => $c->entry_date->toDateString(), 'shop' => $c->shop?->name ?? __('Business'), 'type' => __(ucfirst($c->type)),
            'reason' => $c->reason, 'user' => $c->user->name,
            'in' => $c->type === 'injection' ? (float) $c->amount : 0.0,
            'out' => $c->type === 'withdrawal' ? (float) $c->amount : 0.0,
        ]);
        $in = $rows->sum('in');
        $out = $rows->sum('out');

        return [
            'title' => 'Capital report',
            'columns' => ['date' => 'Date', 'shop' => 'Shop', 'type' => 'Type', 'reason' => 'Reason', 'user' => 'User', 'in' => 'Injection', 'out' => 'Withdrawal'],
            'rows' => $rows, 'totals' => ['in' => $in, 'out' => $out], 'money' => ['in', 'out'],
            'summary' => ['Opening capital' => (float) $opening, 'Movement' => $in - $out, 'Closing capital' => (float) $opening + $in - $out],
        ];
    }

    private function reportDailyClosing(array $f): array
    {
        $q = DailySession::with('shop:id,name', 'opener:id,name', 'closer:id,name');
        $this->shop($this->dates($q, $f, 'business_date'), $f);
        $rows = $q->orderByDesc('business_date')->limit(self::LIMIT)->get()->map(fn (DailySession $s) => [
            'date' => $s->business_date->toDateString(), 'shop' => $s->shop->name, 'status' => __($s->status),
            'opened_by' => $s->opener?->name ?? '—', 'closed_by' => $s->closer?->name ?? '—',
            'sales' => (float) ($s->totals['sales_total'] ?? 0), 'expenses' => (float) ($s->totals['expenses_total'] ?? 0),
            'expected' => (float) ($s->expected_cash ?? 0), 'counted' => (float) ($s->closing_cash ?? 0),
            'difference' => (float) ($s->cash_difference ?? 0), 'exceptions' => $s->exceptions ?? '',
        ]);

        return [
            'title' => 'Daily closing report',
            'columns' => ['date' => 'Date', 'shop' => 'Shop', 'status' => 'Status', 'opened_by' => 'Opened by', 'closed_by' => 'Closed by', 'sales' => 'Sales', 'expenses' => 'Expenses', 'expected' => 'Expected cash', 'counted' => 'Counted cash', 'difference' => 'Difference', 'exceptions' => 'Exceptions'],
            'rows' => $rows,
            'totals' => ['sales' => $rows->sum('sales'), 'expenses' => $rows->sum('expenses'), 'difference' => $rows->sum('difference')],
            'money' => ['sales', 'expenses', 'expected', 'counted', 'difference'],
        ];
    }

    private function reportProfit(array $f): array
    {
        $summary = $this->profit->summary($f['from'], $f['to'], $f['shop_id'] ?? null);
        $group = $f['group_by'] ?? 'product';
        $q = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->join('shops', 'shops.id', '=', 'sales.shop_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$f['from'], $f['to']])
            ->when(! empty($f['shop_id']), fn ($q) => $q->where('sales.shop_id', $f['shop_id']))
            ->when(! empty($f['category_id']), fn ($q) => $q->where('products.category_id', $f['category_id']))
            ->when(! empty($f['product_id']), fn ($q) => $q->where('products.id', $f['product_id']));
        [$key, $label] = match ($group) {
            'category' => ['categories.name', 'Category'],
            'shop' => ['shops.name', 'Shop'],
            'day' => ['sales.sale_date', 'Date'],
            default => ['products.name', 'Product'],
        };
        $rows = $q->selectRaw("COALESCE($key, '".__('Uncategorised')."') as name, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as revenue, SUM(sale_items.cost_total) as cost")
            ->groupBy($key)->orderByDesc('revenue')->limit(self::LIMIT)->get()
            ->map(fn ($r) => [
                'name' => (string) $r->name, 'qty' => (float) $r->qty, 'revenue' => Money::round($r->revenue),
                'cost' => Money::round($r->cost), 'gross' => Money::round($r->revenue - $r->cost),
                'margin' => $r->revenue > 0 ? round(($r->revenue - $r->cost) / $r->revenue * 100, 1).'%' : '—',
            ]);

        return [
            'title' => 'Profit report',
            'columns' => ['name' => $label, 'qty' => 'Qty sold', 'revenue' => 'Revenue (before sale-level discounts)', 'cost' => 'Cost of goods', 'gross' => 'Gross profit', 'margin' => 'Margin'],
            'rows' => $rows,
            'totals' => ['revenue' => $rows->sum('revenue'), 'cost' => $rows->sum('cost'), 'gross' => $rows->sum('gross')],
            'money' => ['revenue', 'cost', 'gross'],
            'qty' => ['qty'],
            'summary' => [
                'Revenue (net of discounts)' => $summary['revenue'],
                'Cost of goods sold' => $summary['cost_of_goods'],
                'Gross profit' => $summary['gross_profit'],
                'Expenses (all)' => $summary['expenses_total'],
                'Expenses reducing profit' => $summary['expenses_reducing_profit'],
                'Net profit' => $summary['net_profit'],
                __('Profit used (:formula)', ['formula' => __($summary['formula'])]) => $summary['profit'],
            ],
            'notice' => $summary['approved'] ? null
                : __('PROVISIONAL: the profit formula (:formula) and costing method (:method) are pending owner approval (OD-001, OD-002). Change them in Settings.',
                    ['formula' => __($summary['formula']), 'method' => __(str_replace('_', ' ', $summary['costing_method']))]),
        ];
    }

    private function reportAllocations(array $f): array
    {
        $q = ProfitAllocation::with('shop:id,name', 'creator:id,name')
            ->where('period_end', '>=', $f['from'])->where('period_start', '<=', $f['to']);
        $this->shop($q, $f);
        $rows = $q->orderByDesc('period_start')->get()->map(fn (ProfitAllocation $a) => [
            'period' => $a->period_start->toDateString().' → '.$a->period_end->toDateString(),
            'shop' => $a->shop?->name ?? __('All shops'), 'status' => __($a->status), 'profit' => (float) $a->profit_amount,
            'primary' => (float) $a->primary_amount, 'primary_label' => $a->primary_label,
            'secondary' => (float) $a->secondary_amount, 'secondary_label' => $a->secondary_label,
        ]);

        return [
            'title' => '60/40 allocation report',
            'columns' => ['period' => 'Period', 'shop' => 'Shop', 'status' => 'Status', 'profit' => 'Profit', 'primary_label' => 'Primary', 'primary' => 'Primary amount', 'secondary_label' => 'Secondary', 'secondary' => 'Secondary amount'],
            'rows' => $rows,
            'totals' => ['profit' => $rows->sum('profit'), 'primary' => $rows->sum('primary'), 'secondary' => $rows->sum('secondary')],
            'money' => ['profit', 'primary', 'secondary'],
        ];
    }

    private function reportAudit(array $f): array
    {
        $q = AuditLog::with('user:id,name', 'shop:id,name');
        $this->shop($this->dates($q, $f, 'created_at'), $f)
            ->when(! empty($f['user_id']), fn ($q) => $q->where('user_id', $f['user_id']))
            ->when(! empty($f['action']), fn ($q) => $q->where('action', 'like', $f['action'].'%'))
            ->when(! empty($f['entity']), fn ($q) => $q->where('entity_type', $f['entity']));
        $rows = $q->orderByDesc('id')->limit(self::LIMIT)->get()->map(fn (AuditLog $a) => [
            'time' => $a->created_at->format('Y-m-d H:i:s'), 'user' => $a->user?->name ?? __('system'), 'shop' => $a->shop?->name ?? '—',
            'action' => $a->action, 'entity' => $a->entity_type ? $a->entity_type.' #'.$a->entity_id : '—',
            'source' => $a->source, 'device' => $a->device_id ?? '', 'ip' => $a->ip_address ?? '',
        ]);

        return [
            'title' => 'Audit report',
            'columns' => ['time' => 'Time', 'user' => 'User', 'shop' => 'Shop', 'action' => 'Action', 'entity' => 'Record', 'source' => 'Source', 'device' => 'Device', 'ip' => 'IP'],
            'rows' => $rows, 'totals' => [], 'money' => [],
        ];
    }

    /** Stream a report as CSV (FR-029 export). */
    public function csv(array $report): StreamedResponse
    {
        $name = $report['type'].'_'.$report['filters']['from'].'_'.$report['filters']['to'].'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map('__', array_values($report['columns'])));
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($k) => $row[$k] ?? '', array_keys($report['columns'])));
            }
            if ($report['totals']) {
                fputcsv($out, array_map(fn ($k) => $report['totals'][$k] ?? '', array_keys($report['columns'])));
            }
            foreach ($report['summary'] ?? [] as $label => $value) {
                fputcsv($out, [__($label), $value]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
