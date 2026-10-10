@props(['title', 'subtitle' => null, 'icon' => null])
<div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex min-w-0 items-center gap-4">
        @if ($icon)
            <div class="icon-chip hidden bg-brand-100 text-brand-700 sm:inline-flex"><x-icon :name="$icon" /></div>
        @endif
        <div class="min-w-0">
            <h1 class="page-title truncate">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
        </div>
    </div>
    <div class="no-print flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
