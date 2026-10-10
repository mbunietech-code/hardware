<x-layout :title="__('Alerts')">
    <x-page-header icon="bell" :title="__('Alerts')" :subtitle="__('Low stock, debts, sync problems and closing reminders.')">
        <a href="{{ request()->fullUrlWithQuery(['all' => request('all') ? null : 1]) }}" class="btn">{{ request('all') ? __('Active only') : __('Show resolved too') }}</a>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn">{{ __('Mark all read') }}</button></form>
    </x-page-header>
    <div class="card divide-y">
        @forelse ($notifications as $n)
            <div class="flex gap-4 px-5 py-3 {{ $n->read_at ? 'opacity-70' : '' }}">
                <span class="badge h-fit {{ str_contains($n->type, 'overdue') || str_contains($n->type, 'conflict') ? 'badge-red' : (str_contains($n->type, 'low') ? 'badge-amber' : 'badge-blue') }}">{{ __(str_replace('_', ' ', $n->type)) }}</span>
                <div class="flex-1">
                    <div class="font-medium">{{ $n->titleText() }}</div>
                    <div class="text-sm text-slate-600">{{ $n->messageText() }}</div>
                    <div class="mt-0.5 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}{{ $n->shop ? ' · '.$n->shop->name : '' }}{{ $n->resolved_at ? ' · '.__('resolved') : '' }}</div>
                </div>
                @if ($n->type === 'sync_conflict' && auth()->user()->isSuperAdmin() && $n->related_id)
                    <a href="{{ route('sync.show', $n->related_id) }}" class="btn btn-sm h-fit">{{ __('Review') }}</a>
                @endif
            </div>
        @empty
            <x-empty :message="__('No alerts. All good!')" />
        @endforelse
    </div>
    {{ $notifications->links() }}
</x-layout>
