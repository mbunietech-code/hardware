@props(['title' => null])
@php
    $user = auth()->user();
    $isAdmin = $user->isSuperAdmin();
    $unread = \App\Models\SystemNotification::visibleTo($user)->whereNull('read_at')->whereNull('resolved_at')->count();
    $conflicts = $isAdmin ? \App\Models\SyncReceipt::where('status', 'conflict')->count() : 0;
    // [route, label, visible, icon]
    $nav = [
        'Daily work' => [
            ['dashboard', 'Dashboard', true, 'home'],
            ['sessions.index', 'Daily sessions', true, 'calendar'],
            ['sales.index', 'Sales', true, 'cart'],
            ['purchases.index', 'Purchases', true, 'truck'],
            ['expenses.index', 'Expenses', true, 'receipt'],
            ['debts.index', 'Debts', true, 'wallet'],
            ['capital.index', 'Capital', $user->hasPermission('record_capital'), 'piggy'],
        ],
        'Inventory' => [
            ['products.index', 'Products', true, 'box'],
            ['stock.index', 'Stock balances', true, 'layers'],
            ['movements.index', 'Stock movements', true, 'arrows'],
            ['categories.index', 'Categories', $user->hasPermission('manage_products'), 'tag'],
            ['customers.index', 'Customers', true, 'users'],
            ['suppliers.index', 'Suppliers', true, 'factory'],
        ],
        'Reports' => [
            ['reports.index', 'Reports', $user->hasPermission('view_reports'), 'chart'],
            ['allocations.index', '60/40 Allocation', $isAdmin, 'pie'],
            ['audit.index', 'Audit trail', $user->hasPermission('view_audit'), 'shield'],
        ],
        'Administration' => [
            ['shops.index', 'Shops', $isAdmin, 'store'],
            ['users.index', 'Users', $isAdmin, 'user'],
            ['expense-categories.index', 'Expense categories', $isAdmin, 'folder'],
            ['sync.index', 'Sync monitor', $isAdmin, 'refresh'],
            ['devices.index', 'Devices', $isAdmin, 'phone'],
            ['settings.edit', 'Settings', $isAdmin, 'settings'],
        ],
    ];
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d655e">
    <title>{{ $title ? $title.' · ' : '' }}{{ \App\Support\Settings::get('business_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-slate-800" x-data="{ menu: false }">
<div class="min-h-screen lg:flex">
    {{-- Sidebar --}}
    <aside class="no-print fixed inset-y-0 left-0 z-40 flex w-[272px] transform flex-col bg-slate-900 text-white transition duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
           :class="menu ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-[72px] shrink-0 items-center gap-3 px-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white ring-1 ring-white/15">
                <x-icon name="store" class="h-5 w-5" />
            </div>
            <div class="min-w-0 leading-tight">
                <div class="truncate text-[15px] font-bold">{{ \App\Support\Settings::get('business_name') }}</div>
                <div class="text-xs text-slate-400">{{ __('Business Management') }}</div>
            </div>
            <button class="icon-btn ml-auto text-white/70 hover:bg-white/10 hover:text-white lg:hidden" @click="menu=false" aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
        </div>

        <div class="px-4 pb-2">
            <a href="{{ route('sales.create') }}" class="btn btn-primary w-full justify-center py-3"><x-icon name="plus" class="h-4 w-4" /> {{ __('New sale') }}</a>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-4">
            @foreach ($nav as $group => $links)
                @php $visible = array_filter($links, fn ($l) => $l[2]); @endphp
                @if ($visible)
                    <div class="space-y-0.5">
                        <div class="px-3 pb-1.5 text-[10.5px] font-bold uppercase tracking-[.14em] text-slate-500">{{ __($group) }}</div>
                        @foreach ($visible as [$route, $label, $show, $icon])
                            <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.').'.*') ? 'active' : '' }}">
                                <x-icon :name="$icon" />
                                <span class="truncate">{{ __($label) }}</span>
                                @if ($route === 'sync.index' && $conflicts)
                                    <span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $conflicts }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl p-2 transition hover:bg-white/8">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-sm font-bold">{{ $initials }}</div>
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-semibold">{{ $user->name }}</div>
                    <div class="truncate text-xs text-slate-400">{{ __($user->roleLabel()) }}{{ $user->shop ? ' · '.$user->shop->name : '' }}</div>
                </div>
            </a>
        </div>
    </aside>
    <div x-show="menu" x-cloak x-transition.opacity @click="menu=false" class="fixed inset-0 z-30 bg-slate-950/50 backdrop-blur-sm lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Top bar --}}
        <header class="no-print sticky top-0 z-20 flex h-[72px] items-center gap-3 border-b border-slate-200/70 bg-white/80 px-4 backdrop-blur-lg lg:px-8">
            <button class="icon-btn lg:hidden" @click="menu=true" aria-label="{{ __('Menu') }}"><x-icon name="menu" /></button>
            <div class="hidden items-center gap-2 text-sm text-slate-500 sm:flex">
                <x-icon name="calendar" class="h-4 w-4" />
                <span>{{ now()->translatedFormat('l, j F Y') }}</span>
            </div>
            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <x-lang-switch />
                <a href="{{ route('notifications.index') }}" class="icon-btn relative" title="{{ __('Alerts') }}">
                    <x-icon name="bell" />
                    @if ($unread)
                        <span class="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>
                <a href="{{ route('profile.edit') }}" class="hidden h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800 sm:flex" title="{{ $user->name }}">{{ $initials }}</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="{{ __('Log out') }}"><x-icon name="logout" /></button></form>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1400px] flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
            @if (session('success'))
                <div class="flash border-emerald-200 bg-emerald-50 text-emerald-800" x-data="{ show: true }" x-show="show">
                    <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0" /><div class="flex-1">{{ session('success') }}</div>
                    <button @click="show=false" class="opacity-60 hover:opacity-100"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endif
            @foreach ((array) session('warnings', []) as $w)
                <div class="flash border-amber-200 bg-amber-50 text-amber-800"><x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" /><div>{{ $w }}</div></div>
            @endforeach
            @if ($errors->any())
                <div class="flash border-rose-200 bg-rose-50 text-rose-800">
                    <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <div class="font-semibold">{{ __('Please fix the following:') }}</div>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 font-normal">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
