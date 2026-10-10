<x-layout :title="__('New sale')">
    <x-page-header :title="__('New sale')" :subtitle="__('Search a product, enter quantity and confirm price. Stock reduces when you save.')" icon="cart">
        <a href="{{ route('sales.index') }}" class="btn"><x-icon name="receipt" class="h-4 w-4" />{{ __('Sales') }}</a>
    </x-page-header>
    @include('partials.pos', ['isSale' => true, 'action' => route('sales.store'), 'createRoute' => route('sales.create'), 'parties' => $customers])
</x-layout>
