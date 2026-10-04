<x-layout :title="__('Shops')">
    <x-page-header icon="store" :title="__('Shops')" :subtitle="__('Deactivate instead of deleting so history is preserved.')" />
    <form method="POST" action="{{ route('shops.store') }}" class="card card-body grid gap-3 md:grid-cols-5">
        @csrf
        <x-field name="name" :label="__('Shop name')" required />
        <x-field name="code" :label="__('Code (used in references)')" required :help="__('Letters/numbers, e.g. MAIN')" />
        <x-field name="location" :label="__('Location')" />
        <x-field name="phone" :label="__('Phone')" />
        <div class="flex items-end"><button class="btn btn-primary">{{ __('Add shop') }}</button></div>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Location') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Users') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($shops as $s)
                <tr>
                    <td class="font-mono">{{ $s->code }}</td>
                    <td><input form="s-{{ $s->id }}" name="name" value="{{ $s->name }}" class="input" required></td>
                    <td><input form="s-{{ $s->id }}" name="location" value="{{ $s->location }}" class="input"></td>
                    <td><input form="s-{{ $s->id }}" name="phone" value="{{ $s->phone }}" class="input"></td>
                    <td>{{ $s->users_count }}</td>
                    <td><input form="s-{{ $s->id }}" type="checkbox" name="is_active" value="1" @checked($s->is_active)></td>
                    <td><form id="s-{{ $s->id }}" method="POST" action="{{ route('shops.update', $s) }}">@csrf @method('PUT')</form><button form="s-{{ $s->id }}" class="btn btn-sm">{{ __('Save') }}</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layout>
