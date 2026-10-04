@php use App\Support\Money; @endphp
<x-layout :title="__('Sales')">
    <x-page-header icon="cart" :title="__('Sales')"><a href="{{ route('sales.create') }}" class="btn btn-primary">{{ __('+ New sale') }}</a></x-page-header>
    <x-filters :shops="$shops">
        <x-field name="search" :label="__('Reference')" :value="request('search')" />
        <x-select name="payment_status" :label="__('Payment')" :options="['paid' => __('Paid'), 'partial' => __('Partial'), 'unpaid' => __('Unpaid')]" :value="request('payment_status')" :placeholder="__('Any')" />
        <x-select name="status" :label="__('Status')" :options="['completed' => __('Completed'), 'voided' => __('Voided')]" :value="request('status')" :placeholder="__('Any')" />
    </x-filters>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Date') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Customer') }}</th><th>{{ __('User') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th>{{ __('Source') }}</th></tr></thead>
            <tbody>
            @forelse ($sales as $s)
                <tr>
                    <td><a class="font-medium text-brand-600" href="{{ route('sales.show', $s) }}">{{ $s->reference }}</a></td>
                    <td>{{ $s->sale_date->format('d M Y') }}</td><td>{{ $s->shop->name }}</td><td>{{ $s->customer?->name ?? __('Walk-in') }}</td>
                    <td>{{ $s->user->name }}</td><td>{{ __(str_replace('_', ' ', $s->payment_method)) }}</td>
                    <td class="text-right">{{ Money::format($s->total) }}</td><td class="text-right">{{ Money::format($s->balance) }}</td>
                    <td><x-status :value="$s->status === 'voided' ? 'voided' : $s->payment_status" /></td>
                    <td><span class="badge">{{ $s->source }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10"><x-empty :message="__('No sales match these filters.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $sales->links() }}
</x-layout>
