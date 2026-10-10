@php use App\Support\Money; use App\Support\Settings; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ __('Receipt') }} {{ $sale->reference }}</title>
<style>body{font-family:monospace;font-size:12px;width:280px;margin:10px auto;color:#000}h1{font-size:15px;text-align:center;margin:0}.c{text-align:center}table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}.r{text-align:right}hr{border:0;border-top:1px dashed #000}@media print{button{display:none}}</style>
</head><body onload="window.print()">
<h1>{{ Settings::get('business_name') }}</h1>
<div class="c">{{ $sale->shop->name }}{{ $sale->shop->location ? ' · '.$sale->shop->location : '' }}<br>{{ $sale->shop->phone }}</div>
<hr>
<div>{{ __('Receipt:') }} {{ $sale->reference }}<br>{{ __('Date:') }} {{ $sale->created_at->format('d/m/Y H:i') }}<br>{{ __('Served by:') }} {{ $sale->user->name }}@if ($sale->customer)<br>{{ __('Customer:') }} {{ $sale->customer->name }}@endif</div>
<hr>
<table>
@foreach ($sale->items as $item)
    <tr><td colspan="2">{{ $item->product->name }}</td></tr>
    <tr><td>{{ Money::formatQty($item->quantity) }} × {{ Money::format($item->unit_price, false) }}@if ((float) $item->discount > 0) − {{ Money::format($item->discount, false) }}@endif</td><td class="r">{{ Money::format($item->line_total, false) }}</td></tr>
@endforeach
</table>
<hr>
<table>
    @if ((float) $sale->discount > 0)<tr><td>{{ __('Discount') }}</td><td class="r">-{{ Money::format($sale->discount, false) }}</td></tr>@endif
    <tr><td><b>{{ __('TOTAL') }}</b></td><td class="r"><b>{{ Money::format($sale->total) }}</b></td></tr>
    <tr><td>{{ __('Paid (:method)', ['method' => __(str_replace('_', ' ', $sale->payment_method))]) }}</td><td class="r">{{ Money::format($sale->amount_paid, false) }}</td></tr>
    @if ((float) $sale->balance > 0)<tr><td>{{ __('Balance due') }}</td><td class="r">{{ Money::format($sale->balance, false) }}</td></tr>@endif
</table>
@if ($sale->status === 'voided')<p class="c"><b>{{ __('*** VOIDED ***') }}</b></p>@endif
<hr><p class="c">{{ Settings::get('receipt_footer') }}</p>
<button onclick="window.print()">{{ __('Print') }}</button>
</body></html>
