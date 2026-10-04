@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'sub' => null, 'trend' => null])
@php
    $tones = [
        'brand' => 'bg-brand-100 text-brand-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'rose' => 'bg-rose-100 text-rose-700',
        'sky' => 'bg-sky-100 text-sky-700',
        'violet' => 'bg-violet-100 text-violet-700',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'stat']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
        </div>
        <div class="icon-chip {{ $tones[$tone] ?? $tones['brand'] }}"><x-icon :name="$icon" /></div>
    </div>
    @if ($trend !== null || $sub)
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            @if ($trend !== null)
                <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 font-semibold {{ $trend >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                    <x-icon :name="$trend >= 0 ? 'up' : 'down'" class="h-3.5 w-3.5" />{{ abs($trend) }}%
                </span>
            @endif
            @if ($sub)<span class="text-slate-500">{{ $sub }}</span>@endif
        </div>
    @endif
</div>
