@php
    use App\Support\Money;
    $f = $report['filters']; $type = $report['type'];
    $fmt = function ($key, $value) use ($report) {
        if (in_array($key, $report['money'] ?? [], true)) return Money::format($value, false);
        if (in_array($key, $report['qty'] ?? [], true)) return Money::formatQty($value);
        return $value;
    };
    $numeric = array_merge($report['money'] ?? [], $report['qty'] ?? []);
@endphp
<x-layout :title="__($report['title'])">
    <x-page-header icon="chart" :title="__($report['title'])" :subtitle="empty($report['no_dates']) ? __('Period: :from to :to', ['from' => $f['from'], 'to' => $f['to']]) : __('As at :time', ['time' => now()->format('d M Y H:i')])">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn"><x-icon name="download" class="h-4 w-4" />Excel</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn"><x-icon name="download" class="h-4 w-4" />PDF</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn">CSV</a>
        <button onclick="window.print()" class="btn"><x-icon name="printer" class="h-4 w-4" />{{ __('Print') }}</button>
        <a href="{{ route('reports.index') }}" class="btn">{{ __('All reports') }}</a>
    </x-page-header>

    <form method="GET" class="card card-body no-print flex flex-wrap items-end gap-3">
        @if (empty($report['no_dates']))
            <div><label class="label">{{ __('From') }}</label><input type="date" name="from" value="{{ $f['from'] }}" class="input"></div>
            <div><label class="label">{{ __('To') }}</label><input type="date" name="to" value="{{ $f['to'] }}" class="input"></div>
        @endif
        @if (auth()->user()->isSuperAdmin())
            <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="$f['shop_id'] ?? ''" :placeholder="__('All shops')" />
        @endif
        @if (in_array($type, ['sales', 'expenses', 'capital', 'audit', 'returns']))
            <x-select name="user_id" :label="__('User')" :options="$options['users']" :value="$f['user_id'] ?? ''" :placeholder="__('Any')" />
        @endif
        @if (in_array($type, ['sales', 'purchases', 'stock', 'stock_movements', 'profit', 'returns']))
            <x-select name="product_id" :label="__('Product')" :options="$options['products']" :value="$f['product_id'] ?? ''" :placeholder="__('Any')" />
        @endif
        @if (in_array($type, ['stock', 'profit']))
            <x-select name="category_id" :label="__('Category')" :options="$options['categories']" :value="$f['category_id'] ?? ''" :placeholder="__('Any')" />
        @endif
        @if ($type === 'expenses')
            <x-select name="category_id" :label="__('Category')" :options="$options['expense_categories']" :value="$f['category_id'] ?? ''" :placeholder="__('Any')" />
        @endif
        @if ($type === 'purchases')
            <x-select name="supplier_id" :label="__('Supplier')" :options="$options['suppliers']" :value="$f['supplier_id'] ?? ''" :placeholder="__('Any')" />
        @endif
        @if (in_array($type, ['sales', 'purchases']))
            <x-select name="payment_status" :label="__('Payment')" :options="['paid' => __('Paid'), 'partial' => __('Partial'), 'unpaid' => __('Unpaid')]" :value="$f['payment_status'] ?? ''" :placeholder="__('Any')" />
            <x-select name="status" :label="__('Status')" :options="['completed' => __('Completed'), 'voided' => __('Voided')]" :value="$f['status'] ?? ''" :placeholder="__('Completed')" />
        @endif
        @if ($type === 'stock')
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="low_only" value="1" @checked(! empty($f['low_only']))> {{ __('Low / out of stock only') }}</label>
        @endif
        @if ($type === 'stock_movements')
            <x-select name="movement_type" :label="__('Type')" :options="array_map('__', $options['movement_types'])" :value="$f['movement_type'] ?? ''" :placeholder="__('Any')" />
        @endif
        @if ($type === 'debts')
            <x-field name="party" :label="__('Party')" :value="$f['party'] ?? ''" />
            <x-select name="debt_type" :label="__('Type')" :options="['receivable' => __('Receivable'), 'payable' => __('Payable')]" :value="$f['debt_type'] ?? ''" :placeholder="__('Any')" />
            <x-select name="status" :label="__('Status')" :options="['paid' => __('Paid'), 'cancelled' => __('Cancelled')]" :value="$f['status'] ?? ''" :placeholder="__('Outstanding')" />
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="overdue_only" value="1" @checked(! empty($f['overdue_only']))> {{ __('Overdue only') }}</label>
        @endif
        @if ($type === 'capital')
            <x-select name="capital_type" :label="__('Type')" :options="['injection' => __('Injection'), 'withdrawal' => __('Withdrawal')]" :value="$f['capital_type'] ?? ''" :placeholder="__('Any')" />
        @endif
        @if ($type === 'profit')
            <x-select name="group_by" :label="__('Group by')" :options="['product' => __('Product'), 'category' => __('Category'), 'shop' => __('Shop'), 'day' => __('Day')]" :value="$f['group_by'] ?? 'product'" :placeholder="false" />
        @endif
        @if ($type === 'audit')
            <x-field name="action" :label="__('Action starts with')" :value="$f['action'] ?? ''" :placeholder="__('e.g. sale.')" />
        @endif
        <button class="btn btn-primary">{{ __('Run report') }}</button>
    </form>

    @if ($report['notice'])
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $report['notice'] }}</div>
    @endif

    @if (! empty($report['summary']))
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($report['summary'] as $label => $value)
                <div class="stat"><div class="stat-label">{{ __($label) }}</div><div class="mt-1 text-lg font-semibold">{{ Money::format($value) }}</div></div>
            @endforeach
        </div>
    @endif

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr>@foreach ($report['columns'] as $key => $label)<th class="{{ in_array($key, $numeric) ? 'text-right' : '' }}">{{ __($label) }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach ($report['columns'] as $key => $label)<td class="{{ in_array($key, $numeric) ? 'text-right whitespace-nowrap' : '' }}">{{ $fmt($key, $row[$key] ?? '') }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}"><x-empty :message="__('No records for these filters.')" /></td></tr>
            @endforelse
            </tbody>
            @if ($report['totals'] && count($report['rows']))
                <tfoot><tr class="bg-slate-50 font-semibold">@foreach ($report['columns'] as $key => $label)<td class="px-3 py-2 {{ in_array($key, $numeric) ? 'text-right' : '' }}">{{ $loop->first ? __('Totals (:n rows)', ['n' => count($report['rows'])]) : (array_key_exists($key, $report['totals']) ? $fmt($key, $report['totals'][$key]) : '') }}</td>@endforeach</tr></tfoot>
            @endif
        </table>
    </div>
    @if (count($report['rows']) >= \App\Services\ReportService::LIMIT)<p class="text-xs text-slate-500">{{ __('Showing the first :n rows. Narrow the filters or export.', ['n' => \App\Services\ReportService::LIMIT]) }}</p>@endif
</x-layout>
