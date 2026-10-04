<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /** Limit a query to the shops the user may see, plus an optional shop filter. */
    protected function scopeShops(Builder $query, Request $request, string $column = 'shop_id'): Builder
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            $query->where($column, $user->shop_id);
        } elseif ($request->filled('shop_id')) {
            $query->where($column, $request->integer('shop_id'));
        }

        return $query;
    }

    protected function dateRange(Builder $query, Request $request, string $column): Builder
    {
        return $query
            ->when($request->filled('from'), fn ($q) => $q->whereDate($column, '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate($column, '<=', $request->date('to')));
    }

    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 50), 1), 200);
    }

    protected function authorizeShopRecord(Request $request, ?int $shopId): void
    {
        abort_unless($request->user()->canAccessShop($shopId), 403, __('You cannot access records of this shop.'));
    }
}
