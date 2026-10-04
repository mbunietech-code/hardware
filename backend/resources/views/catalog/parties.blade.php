@php use App\Support\Money; $store = $kind === 'customers' ? 'customers.store' : 'suppliers.store'; $update = $kind === 'customers' ? 'customers.update' : 'suppliers.update'; @endphp
<x-layout :title="$title">
    <x-page-header icon="users" :title="$title" />
    <form method="POST" action="{{ route($store) }}" class="card card-body grid gap-3 md:grid-cols-5">
        @csrf
        <x-field name="name" :label="__('Name')" required />
        <x-field name="phone" :label="__('Phone')" />
        <x-field name="email" :label="__('Email')" type="email" />
        <x-field name="address" :label="__('Address')" />
        <div class="flex items-end"><button class="btn btn-primary">{{ __('Add') }}</button></div>
    </form>
    <form method="GET" class="flex gap-2"><input name="search" value="{{ request('search') }}" class="input max-w-xs" placeholder="{{ __('Search…') }}"><button class="btn">{{ __('Search') }}</button></form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Email') }}</th><th>{{ __('Address') }}</th>@if ($kind === 'customers')<th class="text-right">{{ __('Owes') }}</th>@endif<th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($parties as $p)
                <tr>
                    <td><input form="f-{{ $p->id }}" name="name" value="{{ $p->name }}" class="input" required></td>
                        <td><input form="f-{{ $p->id }}" name="phone" value="{{ $p->phone }}" class="input"></td>
                        <td><input form="f-{{ $p->id }}" name="email" value="{{ $p->email }}" class="input"></td>
                        <td><input form="f-{{ $p->id }}" name="address" value="{{ $p->address }}" class="input"></td>
                        @if ($kind === 'customers')<td class="text-right font-medium">{{ Money::format($p->owing ?? 0, false) }}</td>@endif
                        <td><input form="f-{{ $p->id }}" type="checkbox" name="is_active" value="1" @checked($p->is_active)></td>
                        <td><form id="f-{{ $p->id }}" method="POST" action="{{ route($update, $p) }}">@csrf @method('PUT')</form><button form="f-{{ $p->id }}" class="btn btn-sm">{{ __('Save') }}</button></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty :message="__('None yet.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $parties->links() }}
</x-layout>
