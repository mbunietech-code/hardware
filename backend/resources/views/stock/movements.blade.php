@php use App\Support\Money; @endphp
<x-layout :title="__('Stock movements')">
    <x-page-header icon="arrows" :title="__('Stock movements')" :subtitle="__('Append-only history of every stock change.')" />
    <x-filters :shops="$shops">
        <x-select name="product_id" :label="__('Product')" :options="$products" :value="request('product_id')" :placeholder="__('Any')" />
        <x-select name="type" :label="__('Type')" :options="array_map('__', \App\Models\StockMovement::TYPES)" :value="request('type')" :placeholder="__('Any')" />
    </x-filters>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Product') }}</th><th>{{ __('Type') }}</th><th class="text-right">{{ __('Change') }}</th><th class="text-right">{{ __('Before') }}</th><th class="text-right">{{ __('After') }}</th><th>{{ __('Source') }}</th><th>{{ __('User') }}</th><th>{{ __('Reason') }}</th></tr></thead>
            <tbody>
            @forelse ($movements as $m)
                <tr>
                    <td class="whitespace-nowrap">{{ $m->created_at->format('d M Y H:i') }}</td><td>{{ $m->shop->name }}</td><td>{{ $m->product->name }}</td>
                    <td>{{ __(\App\Models\StockMovement::TYPES[$m->type] ?? $m->type) }}</td>
                    <td class="text-right font-medium {{ $m->quantity < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ Money::formatQty($m->quantity) }}</td>
                    <td class="text-right">{{ Money::formatQty($m->before_qty) }}</td><td class="text-right">{{ Money::formatQty($m->after_qty) }}</td>
                    <td>
                        @if ($m->source_type === 'sale')<a class="text-brand-600" href="{{ route('sales.show', $m->source_id) }}">{{ __('Sale #') }}{{ $m->source_id }}</a>
                        @elseif ($m->source_type === 'purchase')<a class="text-brand-600" href="{{ route('purchases.show', $m->source_id) }}">{{ __('Purchase #') }}{{ $m->source_id }}</a>
                        @else {{ __(str_replace('_', ' ', $m->source_type ?? '—')) }} @endif
                    </td>
                    <td>{{ $m->user?->name ?? 'system' }}</td><td>{{ $m->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><x-empty :message="__('No movements for this filter.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $movements->links() }}
</x-layout>
