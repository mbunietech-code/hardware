<x-layout :title="$product->exists ? __('Edit product') : __('New product')">
    <x-page-header icon="box" :title="$product->exists ? __('Edit :name', ['name' => $product->name]) : __('New product')" />
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="card card-body grid max-w-3xl gap-4 md:grid-cols-2">
        @csrf @if ($product->exists) @method('PUT') @endif
        <x-field name="code" :label="__('Code / SKU')" :value="$product->code" required />
        <x-field name="name" :label="__('Name')" :value="$product->name" required />
        <x-select name="category_id" :label="__('Category')" :options="$categories" :value="$product->category_id" :placeholder="__('— None —')" />
        <x-field name="unit" :label="__('Unit (pcs, bag, kg, m, tin…)')" :value="$product->unit" required />
        <x-field name="cost_price" :label="__('Cost price')" type="number" :value="$product->cost_price" required />
        <x-field name="selling_price" :label="__('Selling price')" type="number" :value="$product->selling_price" required />
        <x-field name="reorder_level" :label="__('Reorder level')" type="number" :value="$product->reorder_level" :help="__('Low stock alert when quantity reaches this level.')" />
        <x-field name="description" :label="__('Description')" :value="$product->description" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> {{ __('Active (can be sold and purchased)') }}</label>
        <div class="md:col-span-2 flex gap-2"><button class="btn btn-primary">{{ __('Save product') }}</button><a href="{{ url()->previous() }}" class="btn">{{ __('Cancel') }}</a></div>
    </form>
</x-layout>
