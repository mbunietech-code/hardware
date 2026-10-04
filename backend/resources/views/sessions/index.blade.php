@php use App\Support\Money; @endphp
<x-layout :title="__('Daily sessions')">
    <div class="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-8 text-white shadow-soft sm:px-8">
        <img src="{{ asset('images/cement.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
        <div class="absolute inset-0 bg-slate-950/60"></div>
        <div class="relative">
            <h1 class="text-2xl font-bold tracking-tight">{{ __('Daily sessions') }}</h1>
            <p class="mt-1 max-w-xl text-sm text-white/80">{{ __('Open the business day before recording, close it at the end with totals and notes.') }}</p>
        </div>
        <a href="{{ \App\Support\Photos::credit('cement')['url'] }}" target="_blank" rel="noopener" class="absolute right-4 bottom-2 text-[10px] text-white/50 hover:text-white/80">{{ \App\Support\Photos::credit('cement')['text'] }}</a>
    </div>

    <form method="POST" action="{{ route('sessions.open') }}" class="card card-body flex flex-wrap items-end gap-3 no-print">
        @csrf
        <x-select name="shop_id" :label="__('Shop')" :options="$shops" :value="auth()->user()->shop_id" :placeholder="false" required />
        <x-field name="business_date" :label="__('Business date')" type="date" :value="now()->toDateString()" required />
        <x-field name="opening_cash" :label="__('Opening cash')" type="number" value="0" />
        <x-field name="opening_notes" :label="__('Opening notes')" class="min-w-64 flex-1" />
        <button class="btn btn-primary">{{ __('Open business day') }}</button>
    </form>

    <x-filters :shops="$shops">
        <x-select name="status" :label="__('Status')" :options="['open' => __('Open'), 'closed' => __('Closed')]" :value="request('status')" :placeholder="__('Any')" />
    </x-filters>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Status') }}</th><th>{{ __('Opened by') }}</th><th>{{ __('Closed by') }}</th><th class="text-right">{{ __('Sales') }}</th><th class="text-right">{{ __('Expected cash') }}</th><th class="text-right">{{ __('Difference') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($sessions as $s)
                <tr>
                    <td class="font-medium">{{ $s->business_date->translatedFormat('D d M Y') }}</td>
                    <td>{{ $s->shop->name }}</td>
                    <td><x-status :value="$s->status" />@if ($s->reopen_count)<span class="badge badge-amber">{{ __('reopened') }} {{ $s->reopen_count }}×</span>@endif</td>
                    <td>{{ $s->opener?->name }}</td>
                    <td>{{ $s->closer?->name ?? '—' }}</td>
                    <td class="text-right">{{ isset($s->totals['sales_total']) ? Money::format($s->totals['sales_total']) : '—' }}</td>
                    <td class="text-right">{{ $s->expected_cash !== null ? Money::format($s->expected_cash) : '—' }}</td>
                    <td class="text-right {{ (float) $s->cash_difference < 0 ? 'text-red-700' : '' }}">{{ $s->cash_difference !== null ? Money::format($s->cash_difference) : '—' }}</td>
                    <td class="text-right"><a href="{{ route('sessions.show', $s) }}" class="btn btn-sm">{{ $s->isOpen() ? __('Review / close') : __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty :message="__('No business days yet. Open one above.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $sessions->links() }}
</x-layout>
