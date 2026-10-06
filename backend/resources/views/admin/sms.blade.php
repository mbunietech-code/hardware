<x-layout title="SMS">
    <x-page-header icon="phone" title="SMS" :subtitle="__('Messages sent through Beem: debt reminders, daily summaries and tests.')">
        <span class="badge {{ $balance === null ? '' : 'badge-green' }}">{{ __('Beem balance') }}: {{ $balance === null ? __('not connected') : number_format($balance, 0).' '.__('credits') }}</span>
        <a href="{{ route('settings.edit') }}" class="btn"><x-icon name="settings" class="h-4 w-4" />{{ __('Settings') }}</a>
    </x-page-header>
    <div class="flex flex-wrap gap-2">
        @foreach (['' => __('All'), 'sent' => __('sent'), 'failed' => __('failed'), 'skipped' => __('skipped')] as $key => $label)
            <a href="{{ route('sms.index', array_filter(['status' => $key])) }}" class="btn btn-sm {{ request('status', '') === $key ? 'btn-primary' : '' }}">{{ $label }} @if ($key)<span class="opacity-70">{{ $counts[$key] ?? 0 }}</span>@endif</a>
        @endforeach
    </div>
    <div class="card overflow-hidden"><div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Recipient') }}</th><th>{{ __('Type') }}</th><th>{{ __('Message') }}</th><th>{{ __('Status') }}</th><th>{{ __('Shop') }}</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr>
                    <td>{{ $m->created_at->format('d M H:i') }}</td><td class="font-mono text-xs">{{ $m->to }}</td>
                    <td>{{ __(str_replace('_', ' ', $m->purpose)) }}</td>
                    <td class="wrap max-w-md text-xs">{{ $m->message }}</td>
                    <td><span class="badge {{ ['sent' => 'badge-green', 'failed' => 'badge-red'][$m->status] ?? 'badge-amber' }}">{{ __($m->status) }}</span>
                        @if ($m->error)<div class="mt-1 max-w-56 text-xs whitespace-normal text-rose-600">{{ $m->error }}</div>@endif</td>
                    <td>{{ $m->shop?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty :message="__('No SMS yet.')" icon="phone" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    {{ $messages->links() }}
</x-layout>
