<x-layout :title="__('Reports')">
    <x-page-header icon="chart" :title="__('Reports')" :subtitle="__('Choose a report, apply filters, then print or export to CSV (opens in Excel).')" />
    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        @php $desc = [
            'sales' => 'Sales by date, shop, user, product and payment status.',
            'purchases' => 'Purchases by supplier, shop, product and payment status.',
            'stock' => 'Current quantities, low stock and stock value.',
            'stock_movements' => 'Every stock increase and decrease.',
            'expenses' => 'Expenses by category, shop and user.',
            'debts' => 'Outstanding customer and supplier balances.',
            'capital' => 'Capital injections, withdrawals and balance.',
            'daily_closing' => 'Daily totals, cash counts and exceptions.',
            'profit' => 'Gross and net profit by product, category, shop or day.',
            'allocations' => 'Approved and draft 60/40 allocations.',
            'audit' => 'Who did what, when, and from which device.',
        ]; @endphp
        @foreach ($types as $key => $label)
            @php $icon = ['sales' => 'cart', 'purchases' => 'truck', 'stock' => 'layers', 'stock_movements' => 'arrows', 'expenses' => 'receipt', 'debts' => 'wallet', 'capital' => 'piggy', 'daily_closing' => 'calendar', 'profit' => 'trend', 'allocations' => 'pie', 'audit' => 'shield'][$key] ?? 'chart'; @endphp
            <a href="{{ route('reports.show', $key) }}" class="card group flex items-start gap-4 p-5 transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift">
                <div class="icon-chip bg-brand-100 text-brand-700 transition group-hover:bg-brand-600 group-hover:text-white"><x-icon :name="$icon" /></div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-slate-900">{{ __($label) }}</div>
                    <div class="mt-1 text-sm text-slate-500">{{ __($desc[$key] ?? '') }}</div>
                </div>
                <x-icon name="right" class="mt-1 h-5 w-5 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
            </a>
        @endforeach
    </div>
</x-layout>
