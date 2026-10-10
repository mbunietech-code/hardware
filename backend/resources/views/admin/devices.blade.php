<x-layout :title="__('Devices')">
    <x-page-header icon="phone" :title="__('Mobile devices')" :subtitle="__('Devices that have logged in to the mobile app. Revoke a lost or stolen phone to block its access.')" />
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Device') }}</th><th>{{ __('User') }}</th><th>{{ __('Platform') }}</th><th>{{ __('App version') }}</th><th>{{ __('Last seen') }}</th><th>{{ __('Last sync') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($devices as $d)
                <tr>
                    <td>{{ $d->name }}<div class="font-mono text-xs text-slate-400">{{ $d->device_id }}</div></td>
                    <td>{{ $d->user->name }}</td><td>{{ $d->platform }}</td><td>{{ $d->app_version }}</td>
                    <td class="text-xs">{{ $d->last_seen_at?->diffForHumans() ?? '—' }}</td><td class="text-xs">{{ $d->last_sync_at?->diffForHumans() ?? 'never' }}</td>
                    <td><x-status :value="$d->is_revoked ? 'revoked' : 'active'" /></td>
                    <td><form method="POST" action="{{ route('devices.toggle', $d) }}">@csrf<button class="btn btn-sm {{ $d->is_revoked ? '' : 'btn-danger' }}">{{ $d->is_revoked ? __('Restore') : __('Revoke') }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty :message="__('No devices have logged in yet.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $devices->links() }}
</x-layout>
