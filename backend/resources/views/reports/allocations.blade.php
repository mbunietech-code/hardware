@php use App\Support\Money; $profit = max(0, $preview['profit']); @endphp
<x-layout :title="__('60/40 Allocation')">
    <x-page-header icon="pie" :title="__('60/40 Profit allocation')" :subtitle="__('Generate a draft for a period from the configured profit formula, then approve it. Labels, percentages and timing are set in Settings (pending owner decision OD-003/OD-004).')" />
    @if (! $preview['approved'])
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('Profit rules are not yet approved by the owner. Allocations created now are marked provisional in their stored formula configuration.') }}</div>
    @endif
    <div class="grid gap-5 lg:grid-cols-3">
        <form method="GET" class="card card-body space-y-3">
            <h2 class="font-semibold">{{ __('1. Preview a period') }}</h2>
            <x-field name="from" :label="__('From')" type="date" :value="$from" />
            <x-field name="to" :label="__('To')" type="date" :value="$to" />
            <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="request('shop_id')" :placeholder="__('All shops')" />
            <button class="btn w-full">{{ __('Preview') }}</button>
        </form>
        <div class="card card-body lg:col-span-2">
            <h2 class="mb-3 font-semibold">{{ __('2. Preview:') }} {{ $from }} → {{ $to }}</h2>
            <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Revenue') }}</dt><dd>{{ Money::format($preview['revenue']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Cost of goods') }}</dt><dd>{{ Money::format($preview['cost_of_goods']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Expenses reducing profit') }}</dt><dd>{{ Money::format($preview['expenses_reducing_profit']) }}</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ __('Profit (:formula)', ['formula' => __($preview['formula'])]) }}</dt><dd>{{ Money::format($preview['profit']) }}</dd></div>
            </dl>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-brand-50 p-4"><div class="text-xs font-medium uppercase text-brand-700">{{ $labels[0] }} ({{ $percents[0] }}%)</div><div class="mt-1 text-2xl font-semibold">{{ Money::format($profit * $percents[0] / 100) }}</div></div>
                <div class="rounded-lg bg-amber-50 p-4"><div class="text-xs font-medium uppercase text-amber-700">{{ $labels[1] }} ({{ $percents[1] }}%)</div><div class="mt-1 text-2xl font-semibold">{{ Money::format($profit - round($profit * $percents[0] / 100, 2)) }}</div></div>
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
                    <td>{{ $a->period_start->format('d M Y') }} → {{ $a->period_end->format('d M Y') }}</td><td>{{ $a->shop?->name ?? __('All shops') }}</td>
                    <td class="text-right">{{ Money::format($a->profit_amount) }}</td>
                    <td>{{ $a->primary_label }}: <b>{{ Money::format($a->primary_amount, false) }}</b></td>
                    <td>{{ $a->secondary_label }}: <b>{{ Money::format($a->secondary_amount, false) }}</b></td>
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
