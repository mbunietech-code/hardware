<x-layout :title="__('Settings')">
    <x-page-header icon="settings" :title="__('Settings')" :subtitle="__('Items marked OD-xxx are open decisions from the project documents – the owner can change them here at any time. Every change is audited.')" />
    <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
        @csrf @method('PUT')
        @foreach ($groups as $group => $defs)
            <div class="card card-body">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold">{{ __($group) }}</h2>
                    @if ($group === 'SMS')
                        <span class="badge {{ $balance === null ? '' : 'badge-green' }}">{{ __('Beem balance') }}: {{ $balance === null ? __('not connected') : number_format($balance, 0).' '.__('credits') }}</span>
                    @endif
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($defs as $key => $def)
                        @if ($def['type'] === 'bool')
                            <label class="flex items-start gap-2 text-sm md:col-span-2"><input type="checkbox" class="mt-0.5" name="{{ $key }}" value="1" @checked($values[$key])> {{ __($def['label']) }}</label>
                        @elseif ($def['type'] === 'select')
                            <x-select :name="$key" :label="__($def['label'])" :options="array_map('__', $def['options'])" :value="$values[$key]" :placeholder="false" />
                        @elseif ($def['type'] === 'secret')
                            <div>
                                <label class="label" for="{{ $key }}">{{ __($def['label']) }}</label>
                                <input id="{{ $key }}" name="{{ $key }}" type="password" autocomplete="new-password" class="input"
                                       placeholder="{{ $values[$key] ? __('Saved – leave empty to keep') : __('Not set') }}">
                                @if ($values[$key])<label class="mt-1.5 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="clear_{{ $key }}" value="1"> {{ __('Remove saved value') }}</label>@endif
                            </div>
                        @elseif ($def['type'] === 'text')
                            <x-field :name="$key" :label="__($def['label'])" type="textarea" :value="$values[$key]" class="md:col-span-2" />
                        @else
                            <x-field :name="$key" :label="__($def['label'])" :type="$def['type'] === 'int' ? 'number' : 'text'" :value="$values[$key]" />
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
        <button class="btn btn-primary">{{ __('Save settings') }}</button>
    </form>

    <div class="grid gap-5 md:grid-cols-2">
        <form method="POST" action="{{ route('settings.test-email') }}" class="card card-body flex flex-wrap items-end gap-3">
            @csrf
            <x-field name="test_email" :label="__('Send a test email to')" type="email" :value="auth()->user()->email" required class="min-w-56 flex-1" />
            <button class="btn"><x-icon name="check" class="h-4 w-4" />{{ __('Test email') }}</button>
        </form>
        <form method="POST" action="{{ route('settings.test-sms') }}" class="card card-body flex flex-wrap items-end gap-3">
            @csrf
            <x-field name="test_phone" :label="__('Send a test SMS to')" :value="auth()->user()->phone" required class="min-w-56 flex-1" :placeholder="__('e.g. 0712345678')" />
            <button class="btn"><x-icon name="phone" class="h-4 w-4" />{{ __('Test SMS') }}</button>
        </form>
    </div>
</x-layout>
