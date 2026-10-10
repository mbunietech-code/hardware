<x-layout :title="__('Audit trail')">
    <x-page-header icon="shield" :title="__('Audit trail')" :subtitle="__('Read-only record of critical actions. Entries cannot be edited or deleted.')" />
    <x-filters :shops="$shops">
        <x-select name="user_id" :label="__('User')" :options="$users" :value="request('user_id')" :placeholder="__('Any')" />
        <x-field name="action" :label="__('Action starts with')" :value="request('action')" :placeholder="__('sale., stock., auth.')" />
        <x-select name="source" :label="__('Source')" :options="['web' => __('Web'), 'api' => __('API'), 'sync' => __('Mobile sync'), 'system' => __('System')]" :value="request('source')" :placeholder="__('Any')" />
    </x-filters>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Shop') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('Source') }}</th><th>{{ __('Details') }}</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? 'system' }}</td><td>{{ $log->shop?->name ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $log->action }}</td>
                    <td>{{ $log->entity_type ? $log->entity_type.' #'.$log->entity_id : '—' }}</td>
                    <td><span class="badge">{{ $log->source }}</span><div class="text-xs text-slate-400">{{ $log->device_id ?? $log->ip_address }}</div></td>
                    <td>
                        @if ($log->before_value || $log->after_value)
                            <details><summary class="cursor-pointer text-xs text-brand-600">{{ __('view') }}</summary>
                                <div class="mt-1 grid max-w-xl gap-2 text-xs md:grid-cols-2">
                                    @if ($log->before_value)<pre class="overflow-x-auto rounded bg-red-50 p-2">{{ json_encode($log->before_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                                    @if ($log->after_value)<pre class="overflow-x-auto rounded bg-green-50 p-2">{{ json_encode($log->after_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                                </div>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty :message="__('No audit entries for this filter.')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-layout>
