@php
    use App\Support\Money;
    $numeric = array_merge($report['money'] ?? [], $report['qty'] ?? []);
    $fmt = fn ($k, $v) => in_array($k, $report['money'] ?? [], true) ? Money::format($v, false) : (in_array($k, $report['qty'] ?? [], true) ? Money::formatQty($v) : $v);
    $f = $report['filters'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f172a; }
        h1 { font-size: 15px; margin: 0; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #e2f4f1; text-align: left; padding: 5px 4px; font-size: 8px; text-transform: uppercase; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
        .r { text-align: right; }
        tfoot td { font-weight: bold; background: #f1f5f9; }
        .summary td { border: none; padding: 2px 4px; }
        .notice { background: #fef3c7; padding: 6px; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>{{ \App\Support\Settings::get('business_name') }} – {{ __($report['title']) }}</h1>
    <div class="muted">
        {{ empty($report['no_dates']) ? __('Period: :from to :to', ['from' => $f['from'], 'to' => $f['to']]) : __('As at :time', ['time' => now()->format('d M Y H:i')]) }}
        · {{ now()->format('d M Y H:i') }}
    </div>
    @if ($report['notice'])<div class="notice">{{ $report['notice'] }}</div>@endif
    @if (! empty($report['summary']))
        <table class="summary">
            @foreach ($report['summary'] as $label => $value)
                <tr><td>{{ __($label) }}</td><td class="r"><b>{{ Money::format($value) }}</b></td></tr>
            @endforeach
        </table>
    @endif
    <table>
        <thead><tr>@foreach ($report['columns'] as $key => $label)<th class="{{ in_array($key, $numeric) ? 'r' : '' }}">{{ __($label) }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($report['rows'] as $row)
            <tr>@foreach ($report['columns'] as $key => $label)<td class="{{ in_array($key, $numeric) ? 'r' : '' }}">{{ $fmt($key, $row[$key] ?? '') }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($report['columns']) }}">{{ __('No records for these filters.') }}</td></tr>
        @endforelse
        </tbody>
        @if ($report['totals'] && count($report['rows']))
            <tfoot><tr>@foreach ($report['columns'] as $key => $label)<td class="{{ in_array($key, $numeric) ? 'r' : '' }}">{{ $loop->first ? __('Totals (:n rows)', ['n' => count($report['rows'])]) : (array_key_exists($key, $report['totals']) ? $fmt($key, $report['totals'][$key]) : '') }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
</body>
</html>
