<x-layout :title="__('Expense categories')">
    <x-page-header icon="folder" :title="__('Expense categories')" :subtitle="__('Mark which categories reduce profit (pending owner decision OD-005).')" />
    <form method="POST" action="{{ route('expense-categories.store') }}" class="card card-body flex flex-wrap items-end gap-3">
        @csrf
        <x-field name="name" :label="__('New category')" required />
        <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="reduces_profit" value="1" checked> {{ __('Reduces profit') }}</label>
        <button class="btn btn-primary">{{ __('Add') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Reduces profit') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($categories as $c)
                <tr>
                    <td><input form="e-{{ $c->id }}" name="name" value="{{ $c->name }}" class="input" required></td>
                    <td><input form="e-{{ $c->id }}" type="checkbox" name="reduces_profit" value="1" @checked($c->reduces_profit)></td>
                    <td><input form="e-{{ $c->id }}" type="checkbox" name="is_active" value="1" @checked($c->is_active)></td>
                    <td><form id="e-{{ $c->id }}" method="POST" action="{{ route('expense-categories.update', $c) }}">@csrf @method('PUT')</form><button form="e-{{ $c->id }}" class="btn btn-sm">{{ __('Save') }}</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
