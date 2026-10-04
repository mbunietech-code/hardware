@php use App\Support\Money; @endphp
<x-layout :title="__('Purchases')">
    <x-page-header icon="truck" :title="__('Purchases')"><a href="{{ route('purchases.create') }}" class="btn btn-primary">{{ __('+ New purchase') }}</a></x-page-header>
    <x-filters :shops="$shops">
        <x-select name="supplier_id" :label="__('Supplier')" :options="$suppliers" :value="request('supplier_id')" :placeholder="__('Any')" />
        <x-select name="payment_status" :label="__('Payment')" :options="['paid' => __('Paid'), 'partial' => __('Partial'), 'unpaid' => __('Unpaid')]" :value="request('payment_status')" :placeholder="__('Any')" />
    </x-filters>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Date') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('User') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Balance') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($purchases as $p)
                <tr>
                    <td><a class="font-medium text-brand-600" href="{{ route('purchases.show', $p) }}">{{ $p->reference }}</a></td>
                    <td>{{ $p->purchase_date->format('d M Y') }}</td><td>{{ $p->shop->name }}</td><td>{{ $p->supplier?->name ?? '—' }}</td>
                    <td>{{ $p->invoice_number ?? '—' }}</td><td>{{ $p->user->name }}</td>
                    <td class="text-right">{{ Money::format($p->total) }}</td><td class="text-right">{{ Money::format($p->balance) }}</td>
                    <td><x-status :value="$p->status === 'voided' ? 'voided' : $p->payment_status" /></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty :message="__('No purchases yet. Record one to add stock.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $purchases->links() }}
</x-layout>
