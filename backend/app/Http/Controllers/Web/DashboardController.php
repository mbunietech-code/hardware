<?php

namespace App\Http\Controllers\Web;

use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\SyncReceipt;
use App\Models\SystemNotification;
use App\Services\ProfitService;
use Illuminate\Http\Request;

class DashboardController extends WebController
{
    public function __invoke(Request $request, ProfitService $profit)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $shopIds = $user->accessibleShopIds();
        $scope = fn ($q) => $shopIds === null ? $q : $q->whereIn('shop_id', $shopIds);

        $salesToday = $scope(Sale::where('status', 'completed')->whereDate('sale_date', $today));
        $stats = [
            'sales_today' => (float) (clone $salesToday)->sum('total'),
            'sales_count' => (clone $salesToday)->count(),
            'purchases_today' => (float) $scope(Purchase::where('status', 'completed')->whereDate('purchase_date', $today))->sum('total'),
            'expenses_today' => (float) $scope(Expense::where('status', 'active')->whereDate('expense_date', $today))->sum('amount'),
            'receivable' => (float) $scope(Debt::where('type', 'receivable')->whereIn('status', ['open', 'partial']))->sum('balance'),
            'payable' => (float) $scope(Debt::where('type', 'payable')->whereIn('status', ['open', 'partial']))->sum('balance'),
            'month' => $profit->summary($monthStart, $today, null, $shopIds),
            'sales_yesterday' => (float) $scope(Sale::where('status', 'completed')->whereDate('sale_date', now()->subDay()->toDateString()))->sum('total'),
            'expenses_yesterday' => (float) $scope(Expense::where('status', 'active')->whereDate('expense_date', now()->subDay()->toDateString()))->sum('amount'),
        ];
        $pct = fn (float $now, float $before) => $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
        $stats['sales_trend'] = $pct($stats['sales_today'], $stats['sales_yesterday']);
        $stats['expenses_trend'] = $pct($stats['expenses_today'], $stats['expenses_yesterday']);

        $shops = Shop::where('is_active', true)->when($shopIds !== null, fn ($q) => $q->whereIn('id', $shopIds))->orderBy('name')->get()
            ->map(function (Shop $shop) use ($today) {
                $shop->session = DailySession::where('shop_id', $shop->id)->whereDate('business_date', $today)->first();
                $shop->sales_today = (float) Sale::where('shop_id', $shop->id)->where('status', 'completed')->whereDate('sale_date', $today)->sum('total');

                return $shop;
            });

        $lowStock = $scope(StockBalance::query())->with('product', 'shop')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->where('products.reorder_level', '>', 0)->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')
            ->select('stock_balances.*')->orderBy('stock_balances.quantity')->limit(8)->get();
        $stats['low_stock_count'] = $scope(StockBalance::query())
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->where('products.reorder_level', '>', 0)->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')->count();

        $daily = $scope(Sale::where('status', 'completed')->where('sale_date', '>=', now()->subDays(13)->toDateString()))
            ->selectRaw('sale_date, SUM(total) as total')->groupBy('sale_date')->pluck('total', 'sale_date');
        $chart = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->toDateString())
            ->mapWithKeys(fn ($d) => [$d => (float) ($daily[$d] ?? 0)]);

        return view('dashboard', [
            'stats' => $stats,
            'shops' => $shops,
            'lowStock' => $lowStock,
            'chart' => $chart,
            'notifications' => SystemNotification::visibleTo($user)->whereNull('resolved_at')->latest()->limit(6)->get(),
            'conflicts' => $user->isSuperAdmin() ? SyncReceipt::where('status', 'conflict')->count() : 0,
            'recentSales' => $scope(Sale::with('shop:id,name', 'customer:id,name'))->latest('id')->limit(8)->get(),
        ]);
    }
}
