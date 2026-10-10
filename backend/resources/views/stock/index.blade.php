@php use App\Support\Money; @endphp
<x-layout :title="__('Stock')">
    <x-page-header icon="layers" :title="__('Stock balances')" :subtitle="__('Current quantity per shop. Every change is kept in stock movements.')" />
    @can('permission', 'adjust_stock')
    <details class="card no-print" @if ($errors->any()) open @endif>
        <summary class="cursor-pointer px-5 py-3 font-semibold">{{ __('Adjust stock (count, damage, opening stock…)') }}</summary>
        <form method="POST" action="{{ route('stock.adjust') }}" class="grid gap-4 border-t p-5 md:grid-cols-4" x-data="{ mode: 'delta' }">
            @csrf
            <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="auth()->user()->shop_id" :placeholder="false" required />
            <x-select name="product_id" :label="__('Product')" :options="$products" required class="md:col-span-2" />
            <div><label class="label">{{ __('Mode') }}</label><select name="mode" class="input" x-model="mode"><option value="delta">{{ __('Add / remove quantity') }}</option><option value="count">{{ __('Set physical count') }}</option></select></div>
            <div x-show="mode === 'delta'"><x-select name="direction" :label="__('Direction')" :options="['in' => __('Increase (+)'), 'out' => __('Decrease (−)')]" :placeholder="false" /></div>
            <div x-show="mode === 'delta'"><x-field name="quantity" :label="__('Quantity')" type="number" /></div>
            <div x-show="mode === 'count'" x-cloak><x-field name="counted_quantity" :label="__('Counted quantity')" type="number" /></div>
            <x-field name="reason" :label="__('Reason')" required class="md:col-span-2" :help="__('e.g. Opening stock, Damaged, Stock count correction')" />
            <x-field name="notes" :label="__('Notes')" class="md:col-span-2" />
            <div class="md:col-span-4"><button class="btn btn-primary">{{ __('Save adjustment') }}</button></div>
        </form>
    </details>
    @endcan
    <x-filters :shops="$shops" :dates="false">
        <x-field name="search" :label="__('Product')" :value="request('search')" />
        <x-select name="category_id" :label="__('Category')" :options="$categories" :value="request('category_id')" :placeholder="__('Any')" />
        <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="low" value="1" @checked(request('low'))> {{ __('Low stock only') }}</label>
    </x-filters>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Product') }}</th><th>{{ __('Category') }}</th><th>{{ __('Shop') }}</th><th class="text-right">{{ __('Quantity') }}</th><th class="text-right">{{ __('Reorder') }}</th><th class="text-right">{{ __('Avg cost') }}</th><th class="text-right">{{ __('Value') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($balances as $b)
                @php $state = (float) $b->quantity <= 0 ? 'Out of stock' : ((float) $b->quantity <= (float) $b->product->reorder_level ? 'Low' : 'OK'); $cost = (float) $b->avg_cost > 0 ? $b->avg_cost : $b->product->cost_price; @endphp
                <tr>
                    <td class="font-mono text-xs">{{ $b->product->code }}</td>
                    <td><a href="{{ route('products.show', $b->product) }}" class="text-brand-600">{{ $b->product->name }}</a></td>
                    <td>{{ $b->product->category?->name ?? '—' }}</td><td>{{ $b->shop->name }}</td>
                    <td class="text-right font-medium">{{ Money::formatQty($b->quantity) }} {{ $b->product->unit }}</td>
                    <td class="text-right">{{ Money::formatQty($b->product->reorder_level) }}</td>
                    <td class="text-right">{{ Money::format($cost, false) }}</td>
                    <td class="text-right">{{ Money::format((float) $b->quantity * (float) $cost, false) }}</td>
                    <td><x-status :value="$state" /></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty :message="__('No stock records for this filter.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $balances->links() }}
</x-layout>
