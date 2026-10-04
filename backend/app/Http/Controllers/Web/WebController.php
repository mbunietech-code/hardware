<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class WebController extends Controller
{
    protected function scopeShops(Builder $query, Request $request, string $column = 'shop_id'): Builder
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            return $query->where($column, $user->shop_id);
        }

        return $query->when($request->filled('shop_id'), fn ($q) => $q->where($column, $request->integer('shop_id')));
    }

    protected function dateRange(Builder $query, Request $request, string $column): Builder
    {
        return $query
            ->when($request->filled('from'), fn ($q) => $q->whereDate($column, '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate($column, '<=', $request->date('to')));
    }

    /** Shops the current user may pick in forms. */
    protected function shopOptions(Request $request): array
    {
        $user = $request->user();

        return Shop::where('is_active', true)->when(! $user->isSuperAdmin(), fn ($q) => $q->whereKey($user->shop_id))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function authorizeShopRecord(Request $request, ?int $shopId): void
    {
        abort_unless($request->user()->canAccessShop($shopId), 403);
    }
}
