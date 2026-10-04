@php use App\Support\Money; $t = $totals ?? []; @endphp
<x-layout :title="__('Business day')">
    <x-page-header icon="calendar" :title="$session->shop->name.' · '.$session->business_date->translatedFormat('l d M Y')"
                   :subtitle="__('Opened by :name at :time', ['name' => $session->opener?->name ?? '—', 'time' => $session->opened_at?->format('H:i')]).($session->closed_at ? ' · '.__('Closed by :name at :time', ['name' => $session->closer?->name, 'time' => $session->closed_at->format('H:i')]) : '')">
        <x-status :value="$session->status" />
        <button onclick="window.print()" class="btn">{{ __('Print') }}</button>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="stat"><div class="stat-label">{{ __('Sales (:n)', ['n' => $t['sales_count'] ?? 0]) }}</div><div class="stat-value">{{ Money::format($t['sales_total'] ?? 0) }}</div><div class="text-xs text-slate-500">{{ __('Paid') }} {{ Money::format($t['sales_paid'] ?? 0) }} · {{ __('Credit') }} {{ Money::format($t['sales_credit'] ?? 0) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Purchases (:n)', ['n' => $t['purchases_count'] ?? 0]) }}</div><div class="stat-value">{{ Money::format($t['purchases_total'] ?? 0) }}</div><div class="text-xs text-slate-500">{{ __('Paid') }} {{ Money::format($t['purchases_paid'] ?? 0) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Expenses (:n)', ['n' => $t['expenses_count'] ?? 0]) }}</div><div class="stat-value">{{ Money::format($t['expenses_total'] ?? 0) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Expected cash in drawer') }}</div><div class="stat-value">{{ Money::format($t['expected_cash'] ?? 0) }}</div><div class="text-xs text-slate-500">{{ __('Opening cash') }} {{ Money::format($session->opening_cash) }}</div></div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            <h2 class="mb-3 font-semibold">{{ __('Day summary') }}</h2>
            <dl class="grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                @foreach ([
                    'Sales discounts' => $t['sales_discount'] ?? 0,
                    'Cost of goods sold' => $t['cost_of_goods'] ?? 0,
                    'Debt payments received' => $t['debt_payments_received'] ?? 0,
                    'Debt payments made' => $t['debt_payments_made'] ?? 0,
                    'New customer debts' => $t['new_debts_receivable'] ?? 0,
                    'New supplier debts' => $t['new_debts_payable'] ?? 0,
                    'Capital injected' => $t['capital_in'] ?? 0,
                    'Capital withdrawn' => $t['capital_out'] ?? 0,
                ] as $label => $value)
                    <div class="flex justify-between border-b border-dashed py-1"><dt class="text-slate-500">{{ __($label) }}</dt><dd>{{ Money::format($value) }}</dd></div>
                @endforeach
                @foreach (($t['sales_by_method'] ?? []) as $method => $m)
                    <div class="flex justify-between border-b border-dashed py-1"><dt class="text-slate-500">{{ __('Sales via') }} {{ __(str_replace('_', ' ', $method)) }}</dt><dd>{{ Money::format($m['paid']) }} {{ __('paid') }}</dd></div>
                @endforeach
            </dl>
            @if ($session->opening_notes)<p class="mt-4 text-sm"><span class="font-medium">{{ __('Opening notes:') }}</span> {{ $session->opening_notes }}</p>@endif
        </div>

        <div class="card card-body">
            @if ($session->isOpen())
                <h2 class="mb-3 font-semibold">{{ __('Close this day') }}</h2>
                <form method="POST" action="{{ route('sessions.close', $session) }}" class="space-y-3" x-data @submit="if(!confirm(@js(__('Close the business day? Later changes will need a Super Admin.')))) $event.preventDefault()">
                    @csrf
                    <x-field name="closing_cash" :label="__('Counted cash in drawer')" type="number" :help="__('Expected: :amount', ['amount' => Money::format($t['expected_cash'] ?? 0)])" />
                    <x-field name="exceptions" :label="__('Exceptions (stock issues, shortages…)')" type="textarea" />
                    <x-field name="closing_notes" :label="__('Closing notes')" type="textarea" />
                    <button class="btn btn-primary w-full">{{ __('Close business day') }}</button>
                </form>
            @else
                <h2 class="mb-3 font-semibold">{{ __('Closing') }}</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Expected cash') }}</dt><dd>{{ Money::format($session->expected_cash) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Counted cash') }}</dt><dd>{{ $session->closing_cash !== null ? Money::format($session->closing_cash) : '—' }}</dd></div>
                    <div class="flex justify-between font-medium {{ (float) $session->cash_difference < 0 ? 'text-red-700' : 'text-green-700' }}"><dt>{{ __('Difference') }}</dt><dd>{{ $session->cash_difference !== null ? Money::format($session->cash_difference) : '—' }}</dd></div>
                </dl>
                @if ($session->exceptions)<p class="mt-3 text-sm"><span class="font-medium">{{ __('Exceptions:') }}</span> {{ $session->exceptions }}</p>@endif
                @if ($session->closing_notes)<p class="mt-2 text-sm"><span class="font-medium">{{ __('Notes:') }}</span> {{ $session->closing_notes }}</p>@endif
                @if ($session->client_totals)<p class="mt-2 text-xs text-slate-500">{{ __('Closed from mobile device') }} {{ $session->device_id }}.</p>@endif
                @if (auth()->user()->isSuperAdmin())
                    <form method="POST" action="{{ route('sessions.reopen', $session) }}" class="no-print mt-4 space-y-2 border-t pt-4">
                        @csrf
                        <x-field name="reason" :label="__('Reopen for correction – reason')" required />
                        <button class="btn w-full">{{ __('Reopen day') }}</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <div class="border-b px-5 py-3 font-semibold">{{ __('Sales') }}</div>
            <table class="table"><thead><tr><th>{{ __('Ref') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Total') }}</th><th></th></tr></thead><tbody>
            @forelse ($sales as $s)
                <tr><td><a class="text-brand-600" href="{{ route('sales.show', $s) }}">{{ $s->reference }}</a></td><td>{{ $s->customer?->name ?? __('Walk-in') }}</td><td>{{ __(str_replace('_', ' ', $s->payment_method)) }}</td><td class="text-right">{{ Money::format($s->total) }}</td><td><x-status :value="$s->status === 'voided' ? 'voided' : $s->payment_status" /></td></tr>
            @empty <tr><td colspan="5"><x-empty :message="__('No sales.')" /></td></tr> @endforelse
            </tbody></table>
        </div>
        <div class="space-y-5">
            <div class="card overflow-x-auto">
                <div class="border-b px-5 py-3 font-semibold">{{ __('Expenses') }}</div>
                <table class="table"><tbody>
                @forelse ($expenses as $e)
                    <tr><td>{{ $e->category->name }}</td><td>{{ $e->reason }}</td><td class="text-right">{{ Money::format($e->amount) }}</td><td><x-status :value="$e->status" /></td></tr>
                @empty <tr><td><x-empty :message="__('No expenses.')" /></td></tr> @endforelse
                </tbody></table>
            </div>
            <div class="card overflow-x-auto">
                <div class="border-b px-5 py-3 font-semibold">{{ __('Purchases & debt payments') }}</div>
                <table class="table"><tbody>
                @foreach ($purchases as $p)
                    <tr><td><a class="text-brand-600" href="{{ route('purchases.show', $p) }}">{{ $p->reference }}</a></td><td>{{ $p->supplier?->name ?? '—' }}</td><td class="text-right">{{ Money::format($p->total) }}</td></tr>
                @endforeach
                @foreach ($payments as $p)
                    <tr><td>{{ __('Debt payment') }}</td><td>{{ $p->debt->party_name }} ({{ __($p->debt->type) }})</td><td class="text-right">{{ Money::format($p->amount) }}</td></tr>
                @endforeach
                @if ($purchases->isEmpty() && $payments->isEmpty())<tr><td><x-empty :message="__('None.')" /></td></tr>@endif
                </tbody></table>
            </div>
        </div>
    </div>
</x-layout>
