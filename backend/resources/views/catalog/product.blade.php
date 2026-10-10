@php use App\Support\Money; @endphp
<x-layout :title="$product->name">
    <x-page-header icon="box" :title="$product->name" :subtitle="$product->code.' · '.($product->category?->name ?? __('Uncategorised')).' · '.$product->unit">
        <x-status :value="$product->is_active ? 'active' : 'inactive'" />
        @can('permission', 'manage_products')<a href="{{ route('products.edit', $product) }}" class="btn">{{ __('Edit') }}</a>@endcan
    </x-page-header>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="stat"><div class="stat-label">{{ __('Cost price') }}</div><div class="stat-value">{{ Money::format($product->cost_price) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Selling price') }}</div><div class="stat-value">{{ Money::format($product->selling_price) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Total stock') }}</div><div class="stat-value">{{ Money::formatQty($balances->sum('quantity')) }} {{ $product->unit }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Reorder level') }}</div><div class="stat-value">{{ Money::formatQty($product->reorder_level) }}</div></div>
    </div>
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card overflow-x-auto">
            <div class="border-b px-5 py-3 font-semibold">{{ __('Stock by shop') }}</div>
            <table class="table"><thead><tr><th>{{ __('Shop') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Avg cost') }}</th></tr></thead><tbody>
            @foreach ($balances as $b)
                <tr><td>{{ $b->shop->name }}</td><td class="text-right font-medium">{{ Money::formatQty($b->quantity) }}</td><td class="text-right">{{ Money::format($b->avg_cost, false) }}</td></tr>
            @endforeach
            </tbody></table>
        </div>
        <div class="card overflow-x-auto lg:col-span-2">
            <div class="border-b px-5 py-3 font-semibold">{{ __('Recent stock movements') }}</div>
            <table class="table"><thead><tr><th>{{ __('Time') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Type') }}</th><th class="text-right">{{ __('Change') }}</th><th class="text-right">{{ __('After') }}</th><th>{{ __('User') }}</th><th>{{ __('Reason') }}</th></tr></thead><tbody>
            @forelse ($movements as $m)
                <tr><td class="whitespace-nowrap">{{ $m->created_at->format('d M H:i') }}</td><td>{{ $m->shop->name }}</td><td>{{ __(\App\Models\StockMovement::TYPES[$m->type] ?? $m->type) }}</td>
                    <td class="text-right font-medium {{ $m->quantity < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ Money::formatQty($m->quantity) }}</td>
                    <td class="text-right">{{ Money::formatQty($m->after_qty) }}</td><td>{{ $m->user?->name }}</td><td>{{ $m->reason }}</td></tr>
            @empty <tr><td colspan="7"><x-empty :message="__('No movements yet.')" /></td></tr> @endforelse
            </tbody></table>
        </div>
    </div>
</x-layout>
