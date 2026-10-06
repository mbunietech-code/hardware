@props(['title'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ \App\Support\Settings::get('business_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-canvas p-6">
    <div class="flex justify-end"><x-lang-switch /></div>
    <div class="m-auto w-full max-w-sm py-10">
        <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-white"><x-icon name="lock" class="h-6 w-6" /></div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        {{ $slot }}
        <a href="{{ route('login') }}" class="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">← {{ __('Back to sign in') }}</a>
    </div>
</body>
</html>
