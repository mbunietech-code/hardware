<x-layout :title="__('Sync monitor')">
    <x-page-header icon="refresh" :title="__('Sync monitor')" :subtitle="__('Records pushed from mobile devices. Conflicts are held here until a Super Admin accepts or rejects them – nothing is silently overwritten.')" />
    <div class="flex flex-wrap gap-2">
        @foreach (['conflict' => 'Conflicts', 'rejected' => 'Rejected', 'accepted' => 'Accepted', 'all' => 'All'] as $key => $label)
            <a href="{{ route('sync.index', ['status' => $key]) }}" class="btn {{ request('status', 'conflict') === $key ? 'btn-primary' : '' }}">{{ __($label) }} @if ($key !== 'all')<span class="badge">{{ $counts[$key] ?? 0 }}</span>@endif</a>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Received') }}</th><th>{{ __('Entity') }}</th><th>{{ __('User') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Device') }}</th><th>{{ __('Status') }}</th><th>{{ __('Problem') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($receipts as $r)
                <tr>
                    <td class="whitespace-nowrap">{{ $r->created_at->format('d M Y H:i') }}</td>
                    <td class="font-mono text-xs">{{ $r->entity }}</td><td>{{ $r->user?->name }}</td><td>{{ $r->shop?->name ?? '—' }}</td>
                    <td class="text-xs">{{ $r->device_id }}</td>
                    <td><x-status :value="$r->status" />@if ($r->resolution)<div class="text-xs text-slate-400">{{ __(str_replace('_', ' ', $r->resolution)) }}</div>@endif</td>
                    <td class="wrap max-w-md text-xs">{{ $r->error_message }}</td>
                    <td><a href="{{ route('sync.show', $r) }}" class="btn btn-sm">{{ $r->status === 'conflict' ? __('Resolve') : __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty :message="__('Nothing here.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $receipts->links() }}
</x-layout>
