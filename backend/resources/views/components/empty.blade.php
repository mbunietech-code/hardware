@props(['message' => 'Nothing here yet.', 'icon' => 'box'])
<div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><x-icon :name="$icon" class="h-7 w-7" /></div>
    <p class="max-w-sm text-sm text-slate-500">{{ $message }}</p>
    {{ $slot }}
</div>
