@php
    use App\Support\Money;
    $max = max(1, $chart->max());
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning, :name' : ($hour < 17 ? 'Good afternoon, :name' : 'Good evening, :name');
    $firstName = explode(' ', auth()->user()->name)[0];
    $m = $stats['month'];
@endphp
<x-layout :title="__('Dashboard')">
    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-lift sm:p-8">
        <img src="{{ asset('images/hero.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
        <div class="absolute inset-0 bg-slate-950/65"></div>
        <a href="{{ \App\Support\Photos::credit('hero')['url'] }}" target="_blank" rel="noopener" class="absolute right-4 bottom-2 z-10 text-[10px] text-white/50 hover:text-white/80">{{ \App\Support\Photos::credit('hero')['text'] }}</a>
        <div class="relative flex flex-wrap items-center justify-between gap-6">
            <div>
                <p class="text-sm font-medium text-white/75">{{ now()->translatedFormat('l, j F Y') }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{ __($greeting, ['name' => $firstName]) }} 👋</h1>
                <p class="mt-2 max-w-xl text-sm text-white/75">{{ __('Here is how your business is doing today.') }}</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('sales.create') }}" class="btn btn-accent btn-lg"><x-icon name="plus" /> {{ __('Sell now') }}</a>
                    <a href="{{ route('purchases.create') }}" class="btn btn-lg border-white/20 bg-white/10 text-white shadow-none hover:border-white/30 hover:bg-white/20"><x-icon name="truck" /> {{ __('Purchase') }}</a>
                    <a href="{{ route('expenses.index') }}" class="btn btn-lg border-white/20 bg-white/10 text-white shadow-none hover:border-white/30 hover:bg-white/20"><x-icon name="receipt" /> {{ __('Expense') }}</a>
                </div>
            </div>
            <div class="w-full rounded-2xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur sm:w-auto sm:min-w-64">
                <div class="text-xs font-semibold tracking-wider text-white/65 uppercase">{{ __('Sales today') }}</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">{{ Money::format($stats['sales_today']) }}</div>
                <div class="mt-2 flex items-center gap-2 text-xs text-white/75">
                    @if ($stats['sales_trend'] !== null)
                        <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 font-semibold {{ $stats['sales_trend'] >= 0 ? 'bg-emerald-400/20 text-emerald-200' : 'bg-rose-400/20 text-rose-200' }}">
                            <x-icon :name="$stats['sales_trend'] >= 0 ? 'up' : 'down'" class="h-3.5 w-3.5" />{{ abs($stats['sales_trend']) }}%
                        </span>
                        <span>{{ __('vs yesterday') }}</span>
                    @else
                        <span>{{ __(':n sale(s)', ['n' => $stats['sales_count']]) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($conflicts)
        <a href="{{ route('sync.index') }}" class="flash border-rose-200 bg-rose-50 text-rose-800 transition hover:bg-rose-100">
            <x-icon name="refresh" class="mt-0.5 h-5 w-5 shrink-0" />
            <span class="flex-1">{{ __(':n offline record(s) are waiting for conflict review in the Sync monitor →', ['n' => $conflicts]) }}</span>
        </a>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="__('Low stock items')" :value="$stats['low_stock_count']" icon="layers" :tone="$stats['low_stock_count'] ? 'rose' : 'brand'" :sub="$stats['low_stock_count'] ? __('Reorder soon') : __('All products are above reorder level.')" />
        <x-stat :label="__('Purchases today')" :value="Money::format($stats['purchases_today'])" icon="truck" tone="sky" />
        <x-stat :label="__('Expenses today')" :value="Money::format($stats['expenses_today'])" icon="receipt" tone="amber" :trend="$stats['expenses_trend'] === null ? null : -$stats['expenses_trend']" />
        <x-stat :label="__('Customers owe us')" :value="Money::format($stats['receivable'])" icon="wallet" tone="violet" :sub="__('We owe suppliers').' '.Money::format($stats['payable'])" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Chart --}}
        <div class="card xl:col-span-2">
            <div class="card-header">
                <div>
                    <div class="card-title"><x-icon name="trend" class="h-5 w-5 text-brand-600" />{{ __('Sales – last 14 days') }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ __('Total') }} {{ Money::format($chart->sum()) }}</div>
                </div>
                <a href="{{ route('reports.show', 'sales') }}" class="btn btn-sm btn-ghost">{{ __('All reports') }} <x-icon name="right" class="h-4 w-4" /></a>
            </div>
            <div class="card-body">
                <div class="flex h-56 items-end gap-1.5 sm:gap-2.5">
                    @foreach ($chart as $day => $value)
                        @php $isToday = $loop->last; @endphp
                        <div class="group relative flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <div class="pointer-events-none absolute -top-1 z-10 hidden -translate-y-full rounded-lg bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold whitespace-nowrap text-white shadow-lg group-hover:block">
                                {{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('D j M') }} · {{ Money::format($value) }}
                            </div>
                            <div class="flex w-full flex-1 items-end">
                                <div class="w-full rounded-lg transition-all duration-300 {{ $isToday ? 'bg-accent-400' : 'bg-brand-500 opacity-80 group-hover:opacity-100' }}"
                                     style="height: {{ max(3, $value / $max * 100) }}%"></div>
                            </div>
                            <div class="text-[10px] font-medium {{ $isToday ? 'text-accent-500' : 'text-slate-400' }}">{{ \Illuminate\Support\Carbon::parse($day)->format('d') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Month profit --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><x-icon name="pie" class="h-5 w-5 text-brand-600" />{{ __('This month') }}</div>
                @if (! $m['approved'])<span class="badge badge-amber">{{ __('provisional') }}</span>@endif
            </div>
            <div class="card-body space-y-4">
                <div>
                    <div class="text-xs font-medium text-slate-500">{{ __('Profit (:formula)', ['formula' => __($m['formula'])]) }}</div>
                    <div class="mt-1 text-3xl font-bold tracking-tight tabular-nums {{ $m['profit'] < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ Money::format($m['profit']) }}</div>
                </div>
                @php $rev = max(1, $m['revenue']); @endphp
                <div class="space-y-3 text-sm">
                    @foreach ([['Revenue', $m['revenue'], 'bg-brand-500'], ['Cost of goods', $m['cost_of_goods'], 'bg-sky-400'], ['Expenses (reducing profit)', $m['expenses_reducing_profit'], 'bg-amber-400']] as [$label, $val, $color])
                        <div>
                            <div class="flex justify-between gap-2"><span class="text-slate-500">{{ __($label) }}</span><span class="font-semibold whitespace-nowrap tabular-nums">{{ Money::format($val) }}</span></div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $color }}" style="width: {{ min(100, max(0, $val / $rev * 100)) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
                @if (! $m['approved'])<p class="text-xs text-amber-700">{{ __('Provisional – profit rules pending owner approval.') }}</p>@endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Shops --}}
        <div class="card xl:col-span-2">
            <div class="card-header"><div class="card-title"><x-icon name="store" class="h-5 w-5 text-brand-600" />{{ __('Shops today') }}</div></div>
            <div class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5">
                @foreach ($shops as $shop)
                    @php $open = $shop->session?->isOpen(); @endphp
                    <div class="flex items-center gap-4 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                        <div class="icon-chip {{ $shop->session ? ($open ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600') : 'bg-amber-100 text-amber-700' }}">
                            <x-icon :name="$open ? 'unlock' : 'lock'" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold text-slate-900">{{ $shop->name }}</div>
                            <div class="mt-0.5 text-sm font-semibold text-brand-700 tabular-nums">{{ Money::format($shop->sales_today) }}</div>
                            <div class="mt-1">@if ($shop->session)<x-status :value="$shop->session->status" />@else<span class="badge badge-amber">{{ __('not opened') }}</span>@endif</div>
                        </div>
                        @if ($shop->session)
                            <a class="btn btn-sm" href="{{ route('sessions.show', $shop->session) }}">{{ __('View day') }}</a>
                        @else
                            <form method="POST" action="{{ route('sessions.open') }}">@csrf<input type="hidden" name="shop_id" value="{{ $shop->id }}"><button class="btn btn-sm btn-primary">{{ __('Open day') }}</button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Alerts --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><x-icon name="bell" class="h-5 w-5 text-brand-600" />{{ __('Alerts') }}</div>
                <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-ghost">{{ __('All →') }}</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($notifications as $n)
                    <li class="flex gap-3 px-5 py-3.5">
                        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ str_contains($n->type, 'overdue') || str_contains($n->type, 'sync') ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600' }}">
                            <x-icon :name="str_contains($n->type, 'stock') ? 'box' : (str_contains($n->type, 'debt') ? 'wallet' : (str_contains($n->type, 'sync') ? 'refresh' : 'bell'))" class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 text-sm">
                            <div class="font-semibold text-slate-800">{{ $n->titleText() }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ $n->messageText() }}</div>
                        </div>
                    </li>
                @empty
                    <li><x-empty :message="__('No active alerts.')" icon="check" /></li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div class="card-title"><x-icon name="layers" class="h-5 w-5 text-brand-600" />{{ __('Low stock') }}</div>
                <a href="{{ route('stock.index', ['low' => 1]) }}" class="btn btn-sm btn-ghost">{{ __('All →') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table"><thead><tr><th>{{ __('Product') }}</th><th>{{ __('Shop') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Reorder') }}</th></tr></thead><tbody>
                    @forelse ($lowStock as $b)
                        <tr><td class="font-medium text-slate-900">{{ $b->product->name }}</td><td>{{ $b->shop->name }}</td><td class="text-right"><span class="badge badge-red badge-plain">{{ Money::formatQty($b->quantity) }}</span></td><td class="text-right text-slate-500">{{ Money::formatQty($b->product->reorder_level) }}</td></tr>
                    @empty
                        <tr><td colspan="4"><x-empty :message="__('All products are above reorder level.')" icon="check" /></td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header">
                <div class="card-title"><x-icon name="cart" class="h-5 w-5 text-brand-600" />{{ __('Recent sales') }}</div>
                <a href="{{ route('sales.index') }}" class="btn btn-sm btn-ghost">{{ __('All →') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table"><thead><tr><th>{{ __('Ref') }}</th><th>{{ __('Customer') }}</th><th class="text-right">{{ __('Total') }}</th><th></th></tr></thead><tbody>
                    @forelse ($recentSales as $s)
                        <tr>
                            <td><a class="font-semibold text-brand-700 hover:underline" href="{{ route('sales.show', $s) }}">{{ $s->reference }}</a><div class="text-xs text-slate-400">{{ $s->shop->name }}</div></td>
                            <td>{{ $s->customer?->name ?? __('Walk-in') }}</td>
                            <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ Money::format($s->total) }}</td>
                            <td class="text-right"><x-status :value="$s->status === 'voided' ? 'voided' : $s->payment_status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty :message="__('No sales yet.')" icon="cart" /></td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>
    </div>
</x-layout>
