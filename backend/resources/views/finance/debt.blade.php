@php use App\Support\Money; @endphp
<x-layout :title="__('Debt')">
    <x-page-header icon="wallet" :title="$debt->party_name" :subtitle="__(\App\Models\Debt::TYPES[$debt->type]).' · '.$debt->shop->name.' · '.__('recorded by :name', ['name' => $debt->user->name])">
        <x-status :value="$debt->status" />
    </x-page-header>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="stat"><div class="stat-label">{{ __('Original') }}</div><div class="stat-value">{{ Money::format($debt->original_amount) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Paid') }}</div><div class="stat-value">{{ Money::format($debt->paid_amount) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Balance') }}</div><div class="stat-value {{ $debt->isOverdue() ? 'text-red-700' : '' }}">{{ Money::format($debt->balance) }}</div></div>
        <div class="stat"><div class="stat-label">{{ __('Due') }}</div><div class="stat-value text-lg">{{ $debt->due_date?->format('d M Y') ?? __('No due date') }}</div>@if ($debt->isOverdue())<span class="badge badge-red">{{ __('overdue') }}</span>@endif</div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card overflow-x-auto lg:col-span-2">
            <div class="border-b px-5 py-3 font-semibold">{{ __('Payment history') }}</div>
            <table class="table"><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th>{{ __('By') }}</th><th>{{ __('Notes') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead><tbody>
            @forelse ($debt->payments as $p)
                <tr><td>{{ $p->payment_date->format('d M Y') }}</td><td>{{ __(str_replace('_', ' ', $p->payment_method)) }}</td><td>{{ $p->user->name }}</td><td>{{ $p->notes }}</td><td class="text-right">{{ Money::format($p->amount) }}</td></tr>
            @empty <tr><td colspan="5"><x-empty :message="__('No payments yet.')" /></td></tr> @endforelse
            </tbody></table>
            <div class="border-t px-5 py-3 text-sm text-slate-500">
                @if ($debt->sourceRecord) {{ __('Created from') }} {{ __($debt->source_type) }} <a class="text-brand-600" href="{{ $debt->source_type === 'sale' ? route('sales.show', $debt->source_id) : route('purchases.show', $debt->source_id) }}">{{ $debt->sourceRecord->reference }}</a>. @endif
                {{ $debt->notes }}
            </div>
        </div>
        <div class="space-y-5">
            @if (in_array($debt->status, ['open', 'partial']))
                <form method="POST" action="{{ route('debts.pay', $debt) }}" class="card card-body space-y-3">
                    @csrf
                    <h2 class="font-semibold">{{ __('Record payment') }}</h2>
                    <x-field name="amount" :label="__('Amount')" type="number" :value="(float) $debt->balance" required :max="(float) $debt->balance" />
                    <x-field name="payment_date" :label="__('Date')" type="date" :value="now()->toDateString()" />
                    <x-select name="payment_method" :label="__('Method')" :options="['cash' => __('Cash'), 'mobile_money' => __('Mobile money'), 'bank' => __('Bank'), 'card' => __('Card')]" :placeholder="false" />
                    <x-field name="notes" :label="__('Notes')" />
                    <button class="btn btn-primary w-full">{{ __('Save payment') }}</button>
                </form>
                @if ($debt->type === 'receivable' && $debt->party_phone)
                    <form method="POST" action="{{ route('debts.sms', $debt) }}" class="card card-body space-y-3">
                        @csrf
                        <h2 class="font-semibold">{{ __('SMS reminder') }}</h2>
                        <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">{{ app(\App\Services\SmsService::class)->debtReminderText($debt) }}</p>
                        <button class="btn w-full"><x-icon name="phone" class="h-4 w-4" />{{ __('Send to :phone', ['phone' => $debt->party_phone]) }}</button>
                    </form>
                @endif
                @if (auth()->user()->isSuperAdmin())
                    <div class="card card-body"><h2 class="mb-2 font-semibold">{{ __('Cancel debt') }}</h2><x-void-form :action="route('debts.cancel', $debt)" :label="__('Cancel debt')" /></div>
                @endif
            @endif
        </div>
    </div>
</x-layout>
