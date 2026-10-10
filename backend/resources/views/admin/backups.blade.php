<x-layout :title="__('Backups')">
    <x-page-header icon="download" :title="__('Backups')" :subtitle="__('A copy of the whole database is made automatically every night at 23:30. The latest 14 copies are kept.')">
        <form method="POST" action="{{ route('backups.store') }}">@csrf<button class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" />{{ __('Back up now') }}</button></form>
    </x-page-header>
    <div class="card overflow-hidden">
        <table class="table">
            <thead><tr><th>{{ __('File') }}</th><th>{{ __('Created') }}</th><th class="text-right">{{ __('Size') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($backups as $b)
                <tr>
                    <td class="font-mono text-xs">{{ $b['name'] }}</td>
                    <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($b['time'])->timezone(config('app.timezone'))->translatedFormat('D d M Y H:i') }}</td>
                    <td class="text-right">{{ number_format($b['size'] / 1024, 0) }} KB</td>
                    <td class="text-right"><a href="{{ route('backups.download', $b['name']) }}" class="btn btn-sm"><x-icon name="download" class="h-4 w-4" />{{ __('Download') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty :message="__('No backups yet. Click \'Back up now\'.')" icon="download" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card card-body text-sm text-slate-600">
        <div class="card-title mb-2"><x-icon name="shield" class="h-5 w-5 text-brand-600" />{{ __('Keep a copy somewhere else') }}</div>
        {{ __('Download a backup regularly and keep it on another computer, a flash disk or cloud storage. If this computer breaks, the backup is how you recover your data.') }}
    </div>
</x-layout>
