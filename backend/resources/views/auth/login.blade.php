<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d655e">
    <title>{{ __('Sign in') }} · {{ \App\Support\Settings::get('business_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas">
<div class="grid min-h-screen lg:grid-cols-2">
    {{-- Brand panel --}}
    <div class="relative hidden overflow-hidden bg-slate-900 p-12 text-white lg:flex lg:flex-col">
        <img src="{{ asset('images/shop.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
        <div class="absolute inset-0 bg-slate-950/70"></div>
        <div class="relative flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-white ring-1 ring-white/25 backdrop-blur"><x-icon name="store" /></div>
            <div class="text-lg font-bold">{{ \App\Support\Settings::get('business_name') }}</div>
        </div>
        <div class="relative my-auto max-w-md">
            <h1 class="text-4xl leading-tight font-bold tracking-tight">{{ __('Run every shop from one place.') }}</h1>
            <p class="mt-4 text-white/80">{{ __('Sales, stock, expenses and debts – recorded fast in the shop, even without internet, and synced to head office.') }}</p>
            <ul class="mt-8 space-y-4 text-sm">
                @foreach ([['cart', 'Fast selling, even offline'], ['layers', 'Live stock for every shop'], ['chart', 'Clear reports and daily closing'], ['shield', 'Every change is audited']] as [$icon, $text])
                    <li class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20 backdrop-blur"><x-icon :name="$icon" class="h-[18px] w-[18px]" /></span>
                        <span class="font-medium text-white">{{ __($text) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="relative flex items-end justify-between gap-4 text-xs text-white/60">
            <span>{{ __('Hardware Business Management System') }}</span>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex flex-col p-6 sm:p-10">
        <div class="flex justify-end"><x-lang-switch /></div>
        <div class="m-auto w-full max-w-sm py-10">
            <div class="mb-8 lg:hidden">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-white"><x-icon name="store" class="h-6 w-6" /></div>
            </div>
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Welcome back') }} 👋</h2>
            <p class="mt-2 text-sm text-slate-500">{{ __('Sign in to continue to your business dashboard.') }}</p>

            <form method="POST" action="{{ url('login') }}" class="mt-8 space-y-5">
                @csrf
                @if (session('success'))
                    <div class="flash border-emerald-200 bg-emerald-50 text-emerald-800"><x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0" />{{ session('success') }}</div>
                @endif
                @error('login')
                    <div class="flash border-rose-200 bg-rose-50 text-rose-800"><x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />{{ $message }}</div>
                @enderror
                <div>
                    <label class="label" for="login">{{ __('Email or phone') }}</label>
                    <div class="relative">
                        <x-icon name="user" class="pointer-events-none absolute top-1/2 left-3.5 h-[18px] w-[18px] -translate-y-1/2 text-slate-400" />
                        <input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" class="input py-3 pl-11">
                    </div>
                </div>
                <div x-data="{ show: false }">
                    <label class="label" for="password">{{ __('Password') }}</label>
                    <div class="relative">
                        <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-3.5 h-[18px] w-[18px] -translate-y-1/2 text-slate-400" />
                        <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" required autocomplete="current-password" class="input py-3 pr-11 pl-11">
                        <button type="button" @click="show = !show" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 hover:text-slate-700" :aria-label="show ? '' : ''"><x-icon name="eye" class="h-[18px] w-[18px]" /></button>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded accent-brand-600"> {{ __('Keep me signed in') }}</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Forgot password?') }}</a>
                </div>
                <button class="btn btn-primary w-full py-3.5 text-base">{{ __('Sign in') }} <x-icon name="right" class="h-4 w-4" /></button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
