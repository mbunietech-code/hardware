@php use App\Support\Money; $methods = array_map('__', ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank' => 'Bank']); @endphp
<x-layout :title="__('Capital')">
    <x-page-header icon="piggy" :title="__('Capital')" :subtitle="__('Current capital balance: :amount', ['amount' => Money::format($balance)]).' · '.__('Tracked separately from sales and profit.')" />
    <form method="POST" action="{{ route('capital.store') }}" class="card card-body grid gap-4 md:grid-cols-6 no-print">
        @csrf
        <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="auth()->user()->shop_id" :placeholder="auth()->user()->isSuperAdmin() ? __('Business level (no shop)') : false" />
        <x-select name="type" :label="__('Type')" :options="['injection' => __('Injection (money in)'), 'withdrawal' => __('Withdrawal (money out)')]" :placeholder="false" required />
        <x-field name="entry_date" :label="__('Date')" type="date" :value="now()->toDateString()" required />
        <x-field name="amount" :label="__('Amount')" type="number" required />
        <x-select name="payment_method" :label="__('Via')" :options="$methods" :placeholder="false" />
        <x-field name="reason" :label="__('Reason')" required />
        <div class="md:col-span-6"><button class="btn btn-primary">{{ __('Record capital entry') }}</button></div>
    </form>
    <x-filters :shops="$shops" />
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reason') }}</th><th>{{ __('User') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($entries as $c)
                <tr>
                    <td>{{ $c->entry_date->format('d M Y') }}</td><td>{{ $c->shop?->name ?? __('Business') }}</td>
                    <td><span class="badge {{ $c->type === 'injection' ? 'badge-green' : 'badge-amber' }}">{{ __($c->type) }}</span></td>
                    <td>{{ $c->reason }}</td><td>{{ $c->user->name }}</td><td class="text-right font-medium">{{ Money::format($c->amount) }}</td>
                    <td><x-status :value="$c->status" /></td>
                    <td class="relative text-right">
                        @if ($c->status === 'active' && auth()->user()->hasPermission('void_transactions'))
                            <details class="inline-block text-left"><summary class="btn btn-sm">{{ __('Void') }}</summary><div class="absolute right-8 z-10 mt-1 w-96 card card-body"><x-void-form :action="route('capital.void', $c)" /></div></details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty :message="__('No capital movements recorded.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}
</x-layout>
