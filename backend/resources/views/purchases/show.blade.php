@php use App\Support\Money; @endphp
<x-layout :title="$purchase->reference">
    <x-page-header icon="truck" :title="__('Purchase :ref', ['ref' => $purchase->reference])" :subtitle="$purchase->shop->name.' · '.$purchase->purchase_date->format('d M Y').' · '.__('by :name', ['name' => $purchase->user->name])">
        <x-status :value="$purchase->status === 'voided' ? 'voided' : $purchase->payment_status" />
        <button onclick="window.print()" class="btn">{{ __('Print') }}</button>
    </x-page-header>
    @if ($purchase->status === 'voided')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ __('Voided') }} {{ $purchase->voided_at->format('d M Y H:i') }}: {{ $purchase->void_reason }}</div>
    @endif
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Unit cost') }}</th><th class="text-right">{{ __('Line total') }}</th></tr></thead>
            <tbody>
            @foreach ($purchase->items as $item)
                <tr><td>{{ $item->product->name }} <span class="text-xs text-slate-400">{{ $item->product->code }}</span></td>
                    <td class="text-right">{{ Money::formatQty($item->quantity) }} {{ $item->product->unit }}</td>
                    <td class="text-right">{{ Money::format($item->unit_cost) }}</td><td class="text-right font-medium">{{ Money::format($item->line_total) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold"><td colspan="3" class="text-right">{{ __('Total') }}</td><td class="text-right">{{ Money::format($purchase->total) }}</td></tr>
                <tr><td colspan="3" class="text-right">{{ __('Paid (:method)', ['method' => __(str_replace('_', ' ', $purchase->payment_method))]) }}</td><td class="text-right">{{ Money::format($purchase->amount_paid) }}</td></tr>
                <tr><td colspan="3" class="text-right">{{ __('Balance') }}</td><td class="text-right">{{ Money::format($purchase->balance) }}</td></tr>
            </tfoot>
        </table>
    </div>
    <div class="card card-body text-sm">
        <p><span class="text-slate-500">{{ __('Supplier:') }}</span> {{ $purchase->supplier?->name ?? '—' }} · <span class="text-slate-500">{{ __('Invoice:') }}</span> {{ $purchase->invoice_number ?? '—' }}</p>
        @if ($purchase->notes)<p><span class="text-slate-500">{{ __('Notes:') }}</span> {{ $purchase->notes }}</p>@endif
    </div>
    @if ($purchase->status !== 'voided' && auth()->user()->hasPermission('void_transactions'))
        <div class="card card-body no-print"><h2 class="mb-2 font-semibold">{{ __('Correct this purchase') }}</h2><x-void-form :action="route('purchases.void', $purchase)" :label="__('Void purchase & remove stock')" /></div>
    @endif
</x-layout>
