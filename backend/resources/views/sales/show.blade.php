@php use App\Support\Money; @endphp
<x-layout :title="$sale->reference">
    <x-page-header icon="cart" :title="__('Sale :ref', ['ref' => $sale->reference])" :subtitle="$sale->shop->name.' · '.$sale->sale_date->format('d M Y').' · '.__('by :name', ['name' => $sale->user->name]).' · '.__('source: :source', ['source' => $sale->source])">
        <x-status :value="$sale->status === 'voided' ? 'voided' : $sale->payment_status" />
        <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn">{{ __('Print receipt') }}</a>
        <a href="{{ route('sales.create') }}" class="btn btn-primary">{{ __('+ New sale') }}</a>
    </x-page-header>

    @if ($sale->status === 'voided')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ __('Voided') }} {{ $sale->voided_at->format('d M Y H:i') }}: {{ $sale->void_reason }}</div>
    @endif

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Unit price') }}</th><th class="text-right">{{ __('Discount') }}</th><th class="text-right">{{ __('Line total') }}</th><th class="text-right">{{ __('Unit cost') }}</th></tr></thead>
            <tbody>
            @foreach ($sale->items as $item)
                <tr><td>{{ $item->product->name }} <span class="text-xs text-slate-400">{{ $item->product->code }}</span></td>
                    <td class="text-right">{{ Money::formatQty($item->quantity) }} {{ $item->product->unit }}</td>
                    <td class="text-right">{{ Money::format($item->unit_price) }}</td><td class="text-right">{{ Money::format($item->discount) }}</td>
                    <td class="text-right font-medium">{{ Money::format($item->line_total) }}</td><td class="text-right text-slate-500">{{ Money::format($item->unit_cost) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot class="text-sm">
                <tr><td colspan="4" class="text-right">{{ __('Subtotal') }}</td><td class="text-right">{{ Money::format($sale->subtotal) }}</td><td></td></tr>
                <tr><td colspan="4" class="text-right">{{ __('Discount') }}</td><td class="text-right">{{ Money::format($sale->discount) }}</td><td></td></tr>
                <tr class="font-semibold"><td colspan="4" class="text-right">{{ __('Total') }}</td><td class="text-right">{{ Money::format($sale->total) }}</td><td></td></tr>
                <tr><td colspan="4" class="text-right">{{ __('Paid (:method)', ['method' => __(str_replace('_', ' ', $sale->payment_method))]) }}</td><td class="text-right">{{ Money::format($sale->amount_paid) }}</td><td></td></tr>
                <tr><td colspan="4" class="text-right">{{ __('Balance') }}</td><td class="text-right">{{ Money::format($sale->balance) }}</td><td></td></tr>
            </tfoot>
        </table>
    </div>
    <div class="card card-body text-sm">
        <p><span class="text-slate-500">{{ __('Customer:') }}</span> {{ $sale->customer?->name ?? __('Walk-in') }} {{ $sale->customer?->phone }}</p>
        @if ($sale->notes)<p><span class="text-slate-500">{{ __('Notes:') }}</span> {{ $sale->notes }}</p>@endif
        @if ($sale->local_uuid)<p class="text-xs text-slate-400">{{ __('Offline id :id · device :device · synced :at', ['id' => $sale->local_uuid, 'device' => $sale->device_id, 'at' => $sale->synced_at]) }}</p>@endif
    </div>
    @if ($sale->status !== 'voided' && auth()->user()->hasPermission('void_transactions'))
        <div class="card card-body no-print"><h2 class="mb-2 font-semibold">{{ __('Correct this sale') }}</h2><x-void-form :action="route('sales.void', $sale)" :label="__('Void sale & return stock')" /></div>
    @endif
</x-layout>
