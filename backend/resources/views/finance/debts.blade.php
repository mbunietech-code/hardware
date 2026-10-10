@php use App\Support\Money; @endphp
<x-layout :title="__('Debts')">
    <x-page-header icon="wallet" :title="__('Debts')" :subtitle="__('Customers owe: :a', ['a' => Money::format($receivable)]).' · '.__('We owe suppliers: :a', ['a' => Money::format($payable)])" />

    <details class="card no-print" @if ($errors->any()) open @endif>
        <summary class="cursor-pointer px-5 py-3 font-semibold">{{ __('+ Record a debt manually') }}</summary>
        <form method="POST" action="{{ route('debts.store') }}" class="grid gap-4 border-t p-5 md:grid-cols-4" x-data="{ type: 'receivable' }">
            @csrf
            <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="auth()->user()->shop_id" :placeholder="false" required />
            <div><label class="label">{{ __('Type *') }}</label><select name="type" class="input" x-model="type"><option value="receivable">{{ __('Customer owes us') }}</option><option value="payable">{{ __('We owe a supplier') }}</option></select></div>
            <div x-show="type === 'receivable'"><x-select name="customer_id" :label="__('Customer')" :options="$customers" :placeholder="__('— Other party —')" /></div>
            <div x-show="type === 'payable'" x-cloak><x-select name="supplier_id" :label="__('Supplier')" :options="$suppliers" :placeholder="__('— Other party —')" /></div>
            <x-field name="party_name" :label="__('Party name (if not in list)')" />
            <x-field name="party_phone" :label="__('Phone')" />
            <x-field name="amount" :label="__('Amount')" type="number" required />
            <x-field name="debt_date" :label="__('Date')" type="date" :value="now()->toDateString()" />
            <x-field name="due_date" :label="__('Due date')" type="date" />
            <x-field name="notes" :label="__('Notes')" class="md:col-span-3" />
            <div class="md:col-span-4"><button class="btn btn-primary">{{ __('Save debt') }}</button></div>
        </form>
    </details>

    <x-filters :shops="$shops" :dates="false">
        <x-field name="search" :label="__('Party')" :value="request('search')" />
        <x-select name="type" :label="__('Type')" :options="['receivable' => __('Receivable'), 'payable' => __('Payable')]" :value="request('type')" :placeholder="__('Any')" />
        <x-select name="status" :label="__('Status')" :options="['outstanding' => __('Outstanding'), 'paid' => __('Paid'), 'cancelled' => __('Cancelled'), 'all' => __('All')]" :value="request('status', 'outstanding')" :placeholder="false" />
    </x-filters>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Party') }}</th><th>{{ __('Type') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="text-right">{{ __('Original') }}</th><th class="text-right">{{ __('Paid') }}</th><th class="text-right">{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($debts as $d)
                <tr>
                    <td class="font-medium">{{ $d->party_name }}<div class="text-xs text-slate-400">{{ $d->party_phone }}</div></td>
                    <td><span class="badge {{ $d->type === 'receivable' ? 'badge-blue' : 'badge-amber' }}">{{ __($d->type) }}</span></td>
                    <td>{{ $d->shop->name }}</td><td>{{ $d->debt_date->format('d M Y') }}</td>
                    <td class="{{ $d->isOverdue() ? 'font-medium text-red-700' : '' }}">{{ $d->due_date?->format('d M Y') ?? '—' }}</td>
                    <td class="text-right">{{ Money::format($d->original_amount) }}</td><td class="text-right">{{ Money::format($d->paid_amount) }}</td>
                    <td class="text-right font-medium">{{ Money::format($d->balance) }}</td>
                    <td><x-status :value="$d->status" />@if ($d->isOverdue())<span class="badge badge-red">{{ __('overdue') }}</span>@endif</td>
                    <td class="text-right"><a href="{{ route('debts.show', $d) }}" class="btn btn-sm">{{ in_array($d->status, ['open', 'partial']) ? __('Record payment') : __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="10"><x-empty :message="__('No debts for this filter.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $debts->links() }}
</x-layout>
