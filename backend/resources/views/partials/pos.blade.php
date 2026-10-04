{{-- Point-of-sale screen shared by New sale and New purchase.
     Vars: $isSale, $action, $createRoute, $shops, $shopId, $products, $categories, $parties --}}
@php
    $priceKey = $isSale ? 'selling_price' : 'cost_price';
    $discounts = $isSale && \App\Support\Settings::get('discounts_enabled');
    $checkStock = $isSale && \App\Support\Settings::get('negative_stock_policy') === 'block';
    $methods = $isSale
        ? ['cash' => ['Cash', 'cash'], 'mobile_money' => ['Mobile money', 'phone'], 'bank' => ['Bank', 'store'], 'card' => ['Card', 'wallet'], 'credit' => ['Credit', 'calendar']]
        : ['cash' => ['Cash', 'cash'], 'mobile_money' => ['Mobile money', 'phone'], 'bank' => ['Bank', 'store'], 'credit' => ['Credit', 'calendar']];
@endphp
<form method="POST" action="{{ $action }}" x-data="pos(@js($products), '{{ $priceKey }}', { discounts: @js($discounts), checkStock: @js($checkStock) })"
      @submit="if (!cart.length) { $event.preventDefault(); }" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] 2xl:grid-cols-[minmax(0,1fr)_420px]">
    @csrf
    {{-- Products --}}
    <div class="min-w-0 space-y-4">
        <div class="card p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-60 flex-1">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input x-model="search" @keydown.enter.prevent="addFirst()" autofocus class="input py-3 pl-11 text-base" placeholder="{{ __('Search product name or code') }}">
                </div>
                <select name="shop_id" class="input w-auto py-3" onchange="window.location='{{ $createRoute }}?shop_id='+this.value">
                    @foreach ($shops as $id => $name)<option value="{{ $id }}" @selected($shopId == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <button type="button" @click="category=''" :class="category==='' ? 'bg-brand-600 text-white border-transparent' : 'bg-white text-slate-600'" class="btn btn-sm shrink-0 rounded-full">{{ __('All') }}</button>
                @foreach ($categories as $id => $name)
                    <button type="button" @click="category='{{ $id }}'" :class="category==='{{ $id }}' ? 'bg-brand-600 text-white border-transparent' : 'bg-white text-slate-600'" class="btn btn-sm shrink-0 rounded-full">{{ $name }}</button>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <template x-for="p in filtered" :key="p.id">
                <button type="button" @click="add(p)"
                        class="group relative flex flex-col rounded-2xl border bg-white p-4 text-left shadow-soft transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift active:scale-[.98]"
                        :class="inCart(p) ? 'border-brand-500 ring-2 ring-brand-100' : 'border-slate-200/70'">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-sm font-bold text-brand-700" x-text="initials(p.name)"></div>
                        <span x-show="inCart(p)" class="flex h-7 min-w-7 items-center justify-center rounded-full bg-brand-600 px-2 text-xs font-bold text-white" x-text="inCart(p) ? fmt(inCart(p).quantity) : ''"></span>
                    </div>
                    <div class="mt-3 line-clamp-2 text-sm leading-snug font-semibold text-slate-900" x-text="p.name"></div>
                    <div class="mt-0.5 text-xs text-slate-400" x-text="p.code"></div>
                    <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                        <div class="text-[15px] font-bold text-brand-700 tabular-nums" x-text="fmt(p.{{ $priceKey }})"></div>
                        <div class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="p.stock <= 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500'" x-text="fmt(p.stock) + ' ' + p.unit"></div>
                    </div>
                </button>
            </template>
        </div>
        <div x-show="!filtered.length" x-cloak class="card"><x-empty :message="__('No products found.')" icon="search" /></div>
    </div>

    {{-- Cart --}}
    <div id="cart" class="scroll-mt-24 lg:self-start 2xl:sticky 2xl:top-[96px]">
        <div class="card flex flex-col overflow-hidden 2xl:max-h-[calc(100vh-120px)]">
            <div class="card-header">
                <div class="card-title"><x-icon :name="$isSale ? 'cart' : 'truck'" class="h-5 w-5 text-brand-600" />{{ $isSale ? __('Cart') : __('Items received') }}
                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700" x-text="fmt(count)"></span></div>
                <button type="button" x-show="cart.length" @click="clear()" class="btn btn-sm btn-ghost text-rose-600">{{ __('Clear') }}</button>
            </div>

            <div class="flex-1 overflow-x-hidden overflow-y-auto">
                <template x-if="!cart.length">
                    <x-empty :message="__('Tap a product to add it.')" :icon="$isSale ? 'cart' : 'truck'" />
                </template>
                <ul class="divide-y divide-slate-100">
                    <template x-for="(l, i) in cart" :key="l.id">
                        <li class="px-5 py-3.5">
                            <input type="hidden" :name="`items[${i}][product_id]`" :value="l.id">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-slate-900" x-text="l.name"></div>
                                    <div x-show="over(l)" class="mt-0.5 text-xs font-medium text-rose-600">{{ __('Only') }} <span x-text="fmt(l.stock) + ' ' + l.unit"></span> {{ __('in stock') }}</div>
                                </div>
                                <div class="text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums" x-text="fmt(line(l))"></div>
                            </div>
                            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                <div class="flex items-center rounded-xl border border-slate-200 bg-slate-50">
                                    <button type="button" @click="dec(l)" class="flex h-9 w-9 items-center justify-center text-lg font-bold text-slate-500 hover:text-slate-900">−</button>
                                    <input :name="`items[${i}][quantity]`" x-model="l.quantity" type="number" step="any" min="0.001" class="h-9 w-12 border-0 bg-transparent text-center text-sm font-bold focus:ring-0 focus:outline-none">
                                    <button type="button" @click="inc(l)" class="flex h-9 w-9 items-center justify-center text-lg font-bold text-slate-500 hover:text-slate-900">+</button>
                                </div>
                                <span class="text-xs text-slate-400">×</span>
                                <input :name="`items[${i}][price]`" x-model="l.price" type="number" step="any" min="0" class="input h-9 w-28 min-w-0 flex-1 py-1 text-right text-sm" title="{{ $isSale ? __('Unit price') : __('Unit cost') }}">
                                <button type="button" @click="remove(l)" class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><x-icon name="x" class="h-4 w-4" /></button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="space-y-4 border-t border-slate-100 bg-slate-50/60 p-5">
                <div>
                    <div class="label">{{ __('Payment method') }}</div>
                    <input type="hidden" name="payment_method" :value="method">
                    <div class="grid grid-cols-3 gap-2 {{ count($methods) === 5 ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }}">
                        @foreach ($methods as $key => [$label, $icon])
                            <button type="button" @click="method='{{ $key }}'" :class="method==='{{ $key }}' ? 'border-brand-500 bg-brand-50 text-brand-700 ring-2 ring-brand-100' : 'border-slate-200 bg-white text-slate-600'"
                                    class="flex flex-col items-center gap-1 rounded-xl border px-1 py-2.5 text-[11px] font-semibold transition">
                                <x-icon :name="$icon" class="h-5 w-5" />{{ __($label) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">{{ $isSale ? __('Customer') : __('Supplier') }}</label>
                        <select name="{{ $isSale ? 'customer_id' : 'supplier_id' }}" class="input">
                            <option value="">{{ $isSale ? __('Walk-in customer') : __('— None —') }}</option>
                            @foreach ($parties as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">{{ __('Amount paid') }}</label>
                        <input x-model="paid" type="number" step="any" min="0" class="input" :placeholder="method === 'credit' ? '0' : fmt(total)">
                        <input type="hidden" name="amount_paid" :value="paid === '' ? '' : Math.min(parseFloat(paid) || 0, total)">
                    </div>
                    <div class="col-span-2">
                        <input name="{{ $isSale ? 'customer_name' : 'supplier_name' }}" class="input" placeholder="{{ $isSale ? __('…or new customer name') : __('…or new supplier name') }}">
                    </div>
                    @if (! $isSale)
                        <div class="col-span-2"><input name="invoice_number" class="input" placeholder="{{ __('Supplier invoice #') }}"></div>
                    @endif
                    @if ($discounts)
                        <div class="col-span-2 flex items-center justify-between gap-3">
                            <span class="text-sm text-slate-500">{{ __('Sale discount') }}</span>
                            <input name="discount" x-model="discount" type="number" step="any" min="0" class="input w-32 text-right">
                        </div>
                    @endif
                </div>

                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span class="tabular-nums" x-text="fmt(subtotal)"></span></div>
                    <div x-show="balance > 0" class="flex justify-between font-semibold text-amber-700"><span>{{ $isSale ? __('Balance (becomes customer debt)') : __('Balance (becomes supplier debt)') }}</span><span class="tabular-nums" x-text="fmt(balance)"></span></div>
                    <div x-show="change > 0" x-cloak class="flex justify-between font-semibold text-emerald-700"><span>{{ __('Change to give') }}</span><span class="tabular-nums" x-text="fmt(change)"></span></div>
                </div>

                <input type="hidden" name="{{ $isSale ? 'sale_date' : 'purchase_date' }}" value="{{ now()->toDateString() }}">
                <button class="btn btn-primary btn-lg w-full justify-between" :disabled="!cart.length">
                    <span class="flex items-center gap-2"><x-icon name="check" />{{ $isSale ? __('Save sale') : __('Save purchase') }}</span>
                    <span class="text-lg tabular-nums">{{ \App\Support\Settings::get('currency') }} <span x-text="fmt(total)"></span></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Floating summary on small screens --}}
    <div x-show="cart.length" x-cloak class="fixed inset-x-3 bottom-3 z-30 lg:hidden">
        <a href="#cart" class="flex items-center justify-between rounded-2xl bg-brand-700 px-5 py-4 text-white shadow-lift">
            <span class="flex items-center gap-2 font-semibold"><x-icon name="cart" /> <span x-text="fmt(count)"></span> · {{ __('View cart') }}</span>
            <span class="text-lg font-bold tabular-nums">{{ \App\Support\Settings::get('currency') }} <span x-text="fmt(total)"></span></span>
        </a>
    </div>
</form>
