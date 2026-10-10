<x-layout :title="$user->exists ? __('Edit user') : __('New user')">
    <x-page-header icon="user" :title="$user->exists ? __('Edit :name', ['name' => $user->name]) : __('New user')" />
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card card-body grid max-w-3xl gap-4 md:grid-cols-2" x-data="{ role: '{{ old('role', $user->role) }}' }">
        @csrf @if ($user->exists) @method('PUT') @endif
        <x-field name="name" :label="__('Full name')" :value="$user->name" required />
        <div>
            <label class="label">{{ __('Role *') }}</label>
            <select name="role" class="input" x-model="role">@foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}">{{ __($v) }}</option>@endforeach</select>
        </div>
        <x-field name="email" :label="__('Email')" type="email" :value="$user->email" :help="__('Email or phone is used to log in.')" />
        <x-field name="phone" :label="__('Phone')" :value="$user->phone" />
        <x-select name="shop_id" :label="__('Assigned shop')" :options="$shops" :value="$user->shop_id" :placeholder="__('— None —')" />
        <div></div>
        <x-field name="password" label="{{ $user->exists ? __('New password (leave empty to keep)') : __('Password') }}" type="password" :required="! $user->exists" />
        <x-field name="password_confirmation" :label="__('Confirm password')" type="password" />
        <div class="md:col-span-2" x-show="role === 'shop_admin'">
            <div class="label">{{ __('Extra permissions for this Shop Admin') }}</div>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach (\App\Models\User::SHOP_PERMISSIONS as $key => $label)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[{{ $key }}]" value="1" @checked(old("permissions.$key", $user->permissions[$key] ?? false))> {{ __($label) }}</label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Shop Admins can always record sales, purchases, expenses and debts for their own shop.') }}</p>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))> {{ __('Active (can log in)') }}</label>
        <div class="md:col-span-2 flex gap-2"><button class="btn btn-primary">{{ __('Save user') }}</button><a href="{{ route('users.index') }}" class="btn">{{ __('Cancel') }}</a></div>
    </form>
</x-layout>
