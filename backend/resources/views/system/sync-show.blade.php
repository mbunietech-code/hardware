<x-layout :title="__('Sync record')">
    <x-page-header icon="refresh" :title="__('Sync: :entity', ['entity' => $receipt->entity])" :subtitle="__('From :name on device :device', ['name' => $receipt->user?->name ?? '?', 'device' => $receipt->device_id]).' · '.$receipt->created_at->format('d M Y H:i')">
        <x-status :value="$receipt->status" />
    </x-page-header>
    @if ($receipt->error_message)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><b>{{ $receipt->error_code }}:</b> {{ $receipt->error_message }}</div>
    @endif
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            <h2 class="mb-2 font-semibold">{{ __('Payload sent by the device') }}</h2>
            <pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($receipt->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            <p class="mt-2 text-xs text-slate-500">{{ __('Local id:') }} {{ $receipt->local_uuid }} · {{ __('Server id:') }} {{ $receipt->server_id ?? '—' }}</p>
        </div>
        <div class="card card-body space-y-3">
            @if ($receipt->status === 'conflict')
                <h2 class="font-semibold">{{ __('Resolve conflict') }}</h2>
                <p class="text-sm text-slate-600"><b>{{ __('Accept') }}</b>: {{ __('saves the record anyway (allowing negative stock / a closed day, and recalculating that day\'s totals).') }}</p>
                <p class="text-sm text-slate-600"><b>{{ __('Reject') }}</b>: {{ __('discards it; the device will show it as rejected.') }}</p>
                <form method="POST" action="{{ route('sync.resolve', $receipt) }}" class="flex gap-2" x-data @submit="if(!confirm(@js(__('Apply this decision?')))) $event.preventDefault()">
                    @csrf
                    <button name="decision" value="accept" class="btn btn-primary flex-1">{{ __('Accept') }}</button>
                    <button name="decision" value="reject" class="btn btn-danger flex-1">{{ __('Reject') }}</button>
                </form>
            @else
                <p class="text-sm">{{ __('Resolution:') }} {{ __(str_replace('_', ' ', $receipt->resolution ?? '—')) }} {{ $receipt->resolved_at?->format('d M Y H:i') }}</p>
            @endif
        </div>
    </div>
</x-layout>
