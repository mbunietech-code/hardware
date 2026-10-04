<?php

namespace App\Http\Controllers\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockController extends WebController
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request)
    {
        $q = $this->scopeShops(StockBalance::with('product.category', 'shop:id,name'), $request, 'stock_balances.shop_id')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->select('stock_balances.*')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('products.name', 'like', '%'.$request->search.'%')->orWhere('products.code', 'like', '%'.$request->search.'%')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('products.category_id', $request->integer('category_id')))
            ->when($request->boolean('low'), fn ($q) => $q->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level'));

        return view('stock.index', [
            'balances' => $q->orderBy('products.name')->paginate(50)->withQueryString(),
            'shops' => $this->shopOptions($request),
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit'])
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->name} ({$p->code})"]),
        ]);
    }

    public function adjust(Request $request)
    {
        $data = $request->all();
        if (($data['mode'] ?? 'delta') === 'count') {
            unset($data['direction'], $data['quantity']);
        } else {
            unset($data['counted_quantity']);
        }
        $adjustment = $this->stock->adjust($data, $request->user());

        return back()->with('success', __('Stock adjusted: :before → :after.', ['before' => (float) $adjustment->before_qty, 'after' => (float) $adjustment->after_qty]));
    }

    public function movements(Request $request)
    {
        $q = $this->scopeShops(StockMovement::with('shop:id,name', 'product:id,name,code', 'user:id,name'), $request);

        return view('stock.movements', [
            'movements' => $this->dateRange($q, $request, 'created_at')
                ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
                ->latest('id')->paginate(50)->withQueryString(),
            'shops' => $this->shopOptions($request),
            'products' => Product::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
