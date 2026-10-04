@php use App\Support\Money; @endphp
<x-layout :title="__('Products')">
    <x-page-header icon="box" :title="__('Products')" :subtitle="$shopId ? __('Stock shown for :shop', ['shop' => $shops[$shopId] ?? '']) : __('Stock shown across all shops')">
        @can('permission', 'manage_products')<a href="{{ route('products.create') }}" class="btn btn-primary">{{ __('+ New product') }}</a>@endcan
    </x-page-header>
    <x-filters :shops="$shops" :dates="false">
        <x-field name="search" :label="__('Search name or code')" :value="request('search')" />
        <x-select name="category_id" :label="__('Category')" :options="$categories" :value="request('category_id')" :placeholder="__('Any')" />
        <x-select name="active" :label="__('Status')" :options="['1' => __('Active'), '0' => __('Inactive')]" :value="request('active')" :placeholder="__('Any')" />
    </x-filters>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Unit') }}</th><th class="text-right">{{ __('Cost') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Stock') }}</th><th class="text-right">{{ __('Reorder') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr>
                    <td class="font-mono text-xs">{{ $p->code }}</td>
                    <td><a class="font-medium text-brand-600" href="{{ route('products.show', $p) }}">{{ $p->name }}</a></td>
                    <td>{{ $p->category?->name ?? '—' }}</td><td>{{ $p->unit }}</td>
                    <td class="text-right">{{ Money::format($p->cost_price, false) }}</td><td class="text-right">{{ Money::format($p->selling_price, false) }}</td>
                    <td class="text-right font-medium {{ (float) $p->stock <= (float) $p->reorder_level ? 'text-red-700' : '' }}">{{ Money::formatQty($p->stock ?? 0) }}</td>
                    <td class="text-right">{{ Money::formatQty($p->reorder_level) }}</td>
                    <td><x-status :value="$p->is_active ? 'active' : 'inactive'" /></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty :message="__('No products found.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
</x-layout>
