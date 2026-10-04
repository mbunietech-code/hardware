<x-layout :title="__('Settings')">
    <x-page-header icon="settings" :title="__('Settings')" :subtitle="__('Items marked OD-xxx are open decisions from the project documents – the owner can change them here at any time. Every change is audited.')" />
    <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
        @csrf @method('PUT')
        @foreach ($groups as $group => $defs)
            <div class="card card-body">
                <h2 class="mb-4 font-semibold">{{ __($group) }}</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($defs as $key => $def)
                        @if ($def['type'] === 'bool')
                            <label class="flex items-start gap-2 text-sm md:col-span-2"><input type="checkbox" class="mt-0.5" name="{{ $key }}" value="1" @checked($values[$key])> {{ __($def['label']) }}</label>
                        @elseif ($def['type'] === 'select')
                            <x-select :name="$key" :label="__($def['label'])" :options="array_map('__', $def['options'])" :value="$values[$key]" :placeholder="false" />
                        @else
                            <x-field :name="$key" :label="__($def['label'])" :type="$def['type'] === 'int' ? 'number' : 'text'" :value="$values[$key]" />
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
        <button class="btn btn-primary">{{ __('Save settings') }}</button>
    </form>
</x-layout>
