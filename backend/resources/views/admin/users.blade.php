<x-layout :title="__('Users')">
    <x-page-header icon="user" :title="__('Users')"><a href="{{ route('users.create') }}" class="btn btn-primary">{{ __('+ New user') }}</a></x-page-header>
    <form method="GET" class="flex gap-2"><input name="search" value="{{ request('search') }}" class="input max-w-xs" placeholder="{{ __('Search name…') }}"><button class="btn">{{ __('Search') }}</button></form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Login') }}</th><th>{{ __('Role') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Extra permissions') }}</th><th>{{ __('Last login') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="font-medium">{{ $u->name }}</td>
                    <td>{{ $u->email }}<div class="text-xs text-slate-400">{{ $u->phone }}</div></td>
                    <td><span class="badge {{ $u->isSuperAdmin() ? 'badge-blue' : '' }}">{{ __($u->roleLabel()) }}</span></td>
                    <td>{{ $u->shop?->name ?? '—' }}</td>
                    <td class="text-xs">{{ $u->isSuperAdmin() ? __('All') : (collect($u->permissions ?? [])->filter()->keys()->map(fn ($p) => __(\App\Models\User::SHOP_PERMISSIONS[$p] ?? $p))->implode(', ') ?: '—') }}</td>
                    <td class="text-xs">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                    <td><x-status :value="$u->is_active ? 'active' : 'inactive'" /></td>
                    <td><a href="{{ route('users.edit', $u) }}" class="btn btn-sm">{{ __('Edit') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</x-layout>
