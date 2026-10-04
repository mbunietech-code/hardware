<x-layout :title="__('New purchase')">
    <x-page-header :title="__('New purchase')" :subtitle="__('Purchased quantities are added to the shop\'s stock when you save.')" icon="truck">
        <a href="{{ route('purchases.index') }}" class="btn"><x-icon name="receipt" class="h-4 w-4" />{{ __('Purchases') }}</a>
    </x-page-header>
    @include('partials.pos', ['isSale' => false, 'action' => route('purchases.store'), 'createRoute' => route('purchases.create'), 'parties' => $suppliers])
</x-layout>
