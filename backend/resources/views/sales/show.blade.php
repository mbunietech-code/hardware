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
    @if ($sale->returns->isNotEmpty())
        <div class="card overflow-hidden">
            <div class="card-header"><div class="card-title"><x-icon name="refresh" class="h-5 w-5 text-brand-600" />{{ __('Returns') }}</div>
                <span class="text-sm text-slate-500">{{ __('Total returned') }}: <b>{{ Money::format($sale->returned_total) }}</b></span></div>
            <div class="overflow-x-auto"><table class="table">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Date') }}</th><th>{{ __('Items') }}</th><th>{{ __('Reason') }}</th><th class="text-right">{{ __('Return value') }}</th><th class="text-right">{{ __('Taken off debt') }}</th><th class="text-right">{{ __('Refunded') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                @foreach ($sale->returns as $r)
                    <tr>
                        <td class="font-semibold">{{ $r->reference }}</td><td>{{ $r->return_date->format('d M Y') }}</td>
                        <td class="wrap">@foreach ($r->items as $i){{ Money::formatQty($i->quantity) }} × {{ $i->product->name }}@if (! $i->restocked) <span class="badge badge-red badge-plain">{{ __('damaged') }}</span>@endif @if (! $loop->last)<br>@endif @endforeach</td>
                        <td class="wrap">{{ $r->reason }}</td>
                        <td class="text-right">{{ Money::format($r->return_value) }}</td><td class="text-right">{{ Money::format($r->debt_reduction) }}</td>
                        <td class="text-right font-semibold">{{ Money::format($r->refund_amount) }} <span class="text-xs text-slate-400">{{ __(str_replace('_', ' ', $r->refund_method)) }}</span></td>
                        <td>{{ $r->user->name }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif

    @if ($sale->status === 'completed' && auth()->user()->hasPermission('process_returns') && array_sum($returnable) > 0)
        <form method="POST" action="{{ route('sales.returns.store', $sale) }}" class="card no-print overflow-hidden"
              x-data="{ q: {}, unit: @js($unitValues), debt: {{ $debtBalance }},
                        get value() { return Object.entries(this.q).reduce((s, [id, n]) => s + (parseFloat(n) || 0) * (this.unit[id] || 0), 0) },
                        get offDebt() { return Math.min(this.value, this.debt) },
                        get refund() { return Math.max(0, this.value - this.offDebt) },
                        fmt(v) { return Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) } }"
              @submit="if (!value || !confirm(@js(__('Save this return?')))) $event.preventDefault()">
            @csrf
            <div class="card-header">
                <div class="card-title"><x-icon name="refresh" class="h-5 w-5 text-brand-600" />{{ __('Return items') }}</div>
                <span class="text-xs text-slate-500">{{ __('Customer brings goods back') }}</span>
            </div>
            <div class="overflow-x-auto"><table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('Can return') }}</th><th class="text-right">{{ __('Value per unit') }}</th><th>{{ __('Quantity to return') }}</th><th>{{ __('Back to stock?') }}</th></tr></thead>
                <tbody>
                @foreach ($sale->items as $item)
                    @continue(($returnable[$item->id] ?? 0) <= 0)
                    <tr>
                        <td class="font-medium">{{ $item->product->name }}</td>
                        <td class="text-right">{{ Money::formatQty($returnable[$item->id]) }} {{ $item->product->unit }}</td>
                        <td class="text-right">{{ Money::format($unitValues[$item->id]) }}</td>
                        <td><input type="number" step="any" min="0" max="{{ $returnable[$item->id] }}" name="items[{{ $item->id }}][quantity]" x-model="q[{{ $item->id }}]" class="input w-28" placeholder="0"></td>
                        <td><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="items[{{ $item->id }}][restock]" value="1" checked class="h-4 w-4 accent-brand-600"> {{ __('Yes, item is OK') }}</label></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="grid gap-4 border-t border-slate-100 bg-slate-50/60 p-5 md:grid-cols-[1fr_1fr_300px]">
                <x-field name="reason" :label="__('Reason')" required :placeholder="__('e.g. Wrong size, damaged on delivery')" />
                <x-select name="refund_method" :label="__('Refund via')" :options="['cash' => __('Cash'), 'mobile_money' => __('Mobile money'), 'bank' => __('Bank'), 'card' => __('Card')]" :placeholder="false" />
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">{{ __('Return value') }}</span><span class="font-semibold" x-text="fmt(value)"></span></div>
                    <div class="flex justify-between" x-show="debt > 0"><span class="text-slate-500">{{ __('Taken off debt') }}</span><span x-text="fmt(offDebt)"></span></div>
                    <div class="flex justify-between text-base font-bold"><span>{{ __('Refund to customer') }}</span><span x-text="fmt(refund)"></span></div>
                    <button class="btn btn-primary mt-2 w-full" :disabled="!value"><x-icon name="check" class="h-4 w-4" />{{ __('Save return') }}</button>
                </div>
            </div>
        </form>
    @endif

    @if ($sale->status !== 'voided' && $sale->returns->isEmpty() && auth()->user()->hasPermission('void_transactions'))
        <div class="card card-body no-print"><h2 class="mb-2 font-semibold">{{ __('Correct this sale') }}</h2><x-void-form :action="route('sales.void', $sale)" :label="__('Void sale & return stock')" /></div>
    @endif
</x-layout>
