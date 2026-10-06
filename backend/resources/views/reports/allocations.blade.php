@php use App\Support\Money; $profit = max(0, $preview['profit']); @endphp
<x-layout :title="__('60/40 Allocation')">
    <x-page-header icon="pie" :title="__('60/40 Profit allocation')" :subtitle="__(':a% :la · :b% :lb. Change the percentages and names in Settings → Finance.', ['a' => $percents[0], 'la' => __($labels[0]), 'b' => $percents[1], 'lb' => __($labels[1])])">
        @if (auth()->user()->isSuperAdmin())<a href="{{ route('settings.edit') }}" class="btn"><x-icon name="settings" class="h-4 w-4" />{{ __('Settings') }}</a>@endif
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($overview as $o)
            <div class="card card-body">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-500">{{ ['daily' => __('Today'), 'weekly' => __('This week'), 'monthly' => __('This month')][$o['type']] }}</div>
                    <span class="text-xs text-slate-400">{{ $o['from'] === $o['to'] ? $o['from'] : $o['from'].' → '.$o['to'] }}</span>
                </div>
                <div class="mt-2 text-xs text-slate-500">{{ __('Profit') }}</div>
                <div class="text-2xl font-bold tabular-nums {{ $o['profit'] < 0 ? 'text-rose-600' : '' }}">{{ Money::format($o['profit']) }}</div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-brand-50 p-3"><div class="text-[11px] font-semibold text-brand-700 uppercase">{{ __($labels[0]) }} {{ $percents[0] }}%</div><div class="mt-1 font-bold tabular-nums">{{ Money::format($o['primary']) }}</div></div>
                    <div class="rounded-xl bg-slate-100 p-3"><div class="text-[11px] font-semibold text-slate-600 uppercase">{{ __($labels[1]) }} {{ $percents[1] }}%</div><div class="mt-1 font-bold tabular-nums">{{ Money::format($o['secondary']) }}</div></div>
                </div>
                <form method="POST" action="{{ route('allocations.store') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="period_start" value="{{ $o['from'] }}"><input type="hidden" name="period_end" value="{{ $o['to'] }}">
                    <input type="hidden" name="period_type" value="{{ $o['type'] }}"><input type="hidden" name="shop_id" value="{{ request('shop_id') }}">
                    <button class="btn btn-sm w-full"><x-icon name="check" class="h-4 w-4" />{{ __('Save as allocation') }}</button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <form method="GET" class="card card-body space-y-3">
            <h2 class="font-semibold">{{ __('Other period') }}</h2>
            <x-field name="from" :label="__('From')" type="date" :value="$from" />
            <x-field name="to" :label="__('To')" type="date" :value="$to" />
            <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="request('shop_id')" :placeholder="__('All shops')" />
            <button class="btn w-full">{{ __('Preview') }}</button>
        </form>
        <div class="card card-body lg:col-span-2">
            <h2 class="mb-3 font-semibold">{{ __('Preview:') }} {{ $from }} → {{ $to }}</h2>
            <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Revenue') }}</dt><dd>{{ Money::format($preview['revenue']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Cost of goods') }}</dt><dd>{{ Money::format($preview['cost_of_goods']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Expenses reducing profit') }}</dt><dd>{{ Money::format($preview['expenses_reducing_profit']) }}</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ __('Profit (:formula)', ['formula' => __($preview['formula'])]) }}</dt><dd>{{ Money::format($preview['profit']) }}</dd></div>
            </dl>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-brand-50 p-4"><div class="text-xs font-medium uppercase text-brand-700">{{ __($labels[0]) }} ({{ $percents[0] }}%)</div><div class="mt-1 text-2xl font-semibold">{{ Money::format($profit * $percents[0] / 100) }}</div></div>
                <div class="rounded-lg bg-amber-50 p-4"><div class="text-xs font-medium uppercase text-amber-700">{{ __($labels[1]) }} ({{ $percents[1] }}%)</div><div class="mt-1 text-2xl font-semibold">{{ Money::format($profit - round($profit * $percents[0] / 100, 2)) }}</div></div>
            </div>
            <form method="POST" action="{{ route('allocations.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="period_start" value="{{ $from }}"><input type="hidden" name="period_end" value="{{ $to }}"><input type="hidden" name="shop_id" value="{{ request('shop_id') }}">
                <x-field name="notes" :label="__('Notes')" class="flex-1" />
                <button class="btn btn-primary">{{ __('Create draft allocation') }}</button>
            </form>
            @if ($preview['profit'] < 0)<p class="mt-2 text-xs text-red-700">{{ __('Profit is negative for this period, so allocation amounts will be zero.') }}</p>@endif
        </div>
    </div>
    <div class="card overflow-x-auto">
        <div class="border-b px-5 py-3 font-semibold">{{ __('Allocation history') }}</div>
        <table class="table">
            <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Shop') }}</th><th class="text-right">{{ __('Profit') }}</th><th>{{ __('Primary') }}</th><th>{{ __('Secondary') }}</th><th>{{ __('Formula') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created / approved') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($allocations as $a)
                <tr>
                    <td>{{ $a->period_start->format('d M Y') }} → {{ $a->period_end->format('d M Y') }}<div class="text-xs text-slate-400">{{ ['daily' => __('Daily'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'custom' => __('Custom')][$a->period_type] ?? '' }}</div></td><td>{{ $a->shop?->name ?? __('All shops') }}</td>
                    <td class="text-right">{{ Money::format($a->profit_amount) }}</td>
                    <td>{{ __($a->primary_label) }}: <b>{{ Money::format($a->primary_amount, false) }}</b></td>
                    <td>{{ __($a->secondary_label) }}: <b>{{ Money::format($a->secondary_amount, false) }}</b></td>
                    <td class="text-xs">{{ $a->formula_config['profit_formula'] ?? '' }} / {{ $a->formula_config['costing_method'] ?? '' }}@if (empty($a->formula_config['rules_approved'])) <span class="badge badge-amber">{{ __('provisional') }}</span>@endif</td>
                    <td><x-status :value="$a->status" /></td>
                    <td class="text-xs">{{ $a->creator->name }}@if ($a->approver)<br>✔ {{ $a->approver->name }} {{ $a->approved_at->format('d M') }}@endif</td>
                    <td class="whitespace-nowrap">
                        @if ($a->status === 'draft')
                            <form method="POST" action="{{ route('allocations.status', [$a, 'approved']) }}" class="inline">@csrf<button class="btn btn-sm btn-primary">{{ __('Approve') }}</button></form>
                            <form method="POST" action="{{ route('allocations.status', [$a, 'cancelled']) }}" class="inline">@csrf<button class="btn btn-sm">{{ __('Cancel') }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty :message="__('No allocations yet.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $allocations->links() }}
</x-layout>
