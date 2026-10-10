@props(['dark' => false])
{{-- EN / SW language switch (stored in a cookie, works before and after login) --}}
<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 rounded-xl p-1 text-xs font-bold '.($dark ? 'bg-white/10 ring-1 ring-white/20' : 'bg-slate-100')]) }} role="group" aria-label="{{ __('Language') }}">
    <x-icon name="globe" class="mx-1 h-4 w-4 {{ $dark ? 'text-white/70' : 'text-slate-400' }}" />
    @foreach (\App\Http\Middleware\SetLocale::SUPPORTED as $code => $name)
        <a href="{{ route('locale.switch', $code) }}" title="{{ $name }}"
           class="rounded-lg px-2.5 py-1.5 transition {{ app()->getLocale() === $code ? ($dark ? 'bg-white text-brand-900 shadow' : 'bg-white text-brand-700 shadow-sm') : ($dark ? 'text-white/80 hover:text-white' : 'text-slate-500 hover:text-slate-800') }}">{{ strtoupper($code) }}</a>
    @endforeach
</div>
