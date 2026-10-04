@php use App\Support\Money; $methods = array_map('__', ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank' => 'Bank', 'card' => 'Card']); @endphp
<x-layout :title="__('Expenses')">
    <x-page-header icon="receipt" :title="__('Expenses')" :subtitle="__('Total for filter: :amount', ['amount' => Money::format($total)])" />
    <form method="POST" action="{{ route('expenses.store') }}" class="card card-body grid gap-4 md:grid-cols-6 no-print">
        @csrf
        <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="auth()->user()->shop_id" :placeholder="false" required />
        <x-field name="expense_date" :label="__('Date')" type="date" :value="now()->toDateString()" required />
        <x-select name="expense_category_id" :label="__('Category')" :options="$categories" required />
        <x-field name="amount" :label="__('Amount')" type="number" required />
        <x-select name="payment_method" :label="__('Paid via')" :options="$methods" :placeholder="false" />
        <x-field name="reason" :label="__('Reason')" required />
        <div class="md:col-span-6"><button class="btn btn-primary">{{ __('Record expense') }}</button></div>
    </form>
    <x-filters :shops="$shops">
        <x-select name="category_id" :label="__('Category')" :options="$categories" :value="request('category_id')" :placeholder="__('Any')" />
        <x-select name="status" :label="__('Status')" :options="['active' => __('Active'), 'voided' => __('Voided')]" :value="request('status', 'active')" :placeholder="false" />
    </x-filters>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Category') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Method') }}</th><th>{{ __('User') }}</th><th class="text-right">{{ __('Amount') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($expenses as $e)
                <tr>
                    <td>{{ $e->expense_date->format('d M Y') }}</td><td>{{ $e->shop->name }}</td><td>{{ $e->category->name }}</td><td class="wrap">{{ $e->reason }}</td>
                    <td>{{ __(str_replace('_', ' ', $e->payment_method)) }}</td><td>{{ $e->user->name }}</td><td class="text-right font-medium">{{ Money::format($e->amount) }}</td>
                    <td class="relative text-right">
                        @if ($e->status === 'active' && auth()->user()->hasPermission('void_transactions'))
                            <details class="inline-block text-left"><summary class="btn btn-sm">{{ __('Void') }}</summary><div class="absolute right-8 z-10 mt-1 w-96 card card-body"><x-void-form :action="route('expenses.void', $e)" /></div></details>
                        @elseif ($e->status === 'voided')<span title="{{ $e->void_reason }}"><x-status value="voided" /></span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty :message="__('No expenses recorded for this filter.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $expenses->links() }}
</x-layout>
