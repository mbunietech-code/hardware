<x-layout :title="__('Categories')">
    <x-page-header icon="tag" :title="__('Product categories')" />
    <form method="POST" action="{{ route('categories.store') }}" class="card card-body flex flex-wrap items-end gap-3">
        @csrf
        <x-field name="name" :label="__('New category name')" required />
        <x-field name="description" :label="__('Description')" class="min-w-64 flex-1" />
        <button class="btn btn-primary">{{ __('Add category') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Description') }}</th><th>{{ __('Products') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($categories as $c)
                <tr>
                    <td><input form="f-{{ $c->id }}" name="name" value="{{ $c->name }}" class="input"></td>
                        <td><input form="f-{{ $c->id }}" name="description" value="{{ $c->description }}" class="input"></td>
                        <td>{{ $c->products_count }}</td>
                        <td><input form="f-{{ $c->id }}" type="checkbox" name="is_active" value="1" @checked($c->is_active)></td>
                        <td><form id="f-{{ $c->id }}" method="POST" action="{{ route('categories.update', $c) }}">@csrf @method('PUT')</form><button form="f-{{ $c->id }}" class="btn btn-sm">{{ __('Save') }}</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
