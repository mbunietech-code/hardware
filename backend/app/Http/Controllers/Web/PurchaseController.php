<?php

namespace App\Http\Controllers\Web;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends WebController
{
    public function __construct(private PurchaseService $service) {}

    public function index(Request $request)
    {
        $q = $this->scopeShops(Purchase::with('shop:id,name', 'supplier:id,name', 'user:id,name'), $request);

        return view('purchases.index', [
            'purchases' => $this->dateRange($q, $request, 'purchase_date')
                ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->payment_status))
                ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
                ->latest('id')->paginate(30)->withQueryString(),
            'shops' => $this->shopOptions($request),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request)
    {
        $shops = $this->shopOptions($request);
        $shopId = (int) ($request->input('shop_id') ?: $request->user()->shop_id ?: array_key_first($shops));
        $stock = \App\Models\StockBalance::where('shop_id', $shopId)->pluck('quantity', 'product_id');

        return view('purchases.create', [
            'session' => \App\Models\DailySession::where('shop_id', $shopId)->whereDate('business_date', now()->toDateString())->first(),
            'shops' => $shops,
            'shopId' => $shopId,
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'category_id', 'code', 'name', 'unit', 'cost_price'])
                ->map(fn ($p) => $p->toArray() + ['stock' => (float) ($stock[$p->id] ?? 0)]),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['items'] = collect($data['items'] ?? [])->map(fn ($i) => [
            'product_id' => $i['product_id'] ?? null, 'quantity' => $i['quantity'] ?? null, 'unit_cost' => $i['price'] ?? null,
        ])->filter(fn ($i) => $i['product_id'])->values()->all();
        if (($data['amount_paid'] ?? '') === '') {
            unset($data['amount_paid']);
        }
        $purchase = $this->service->create($data, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', __('Purchase :ref saved and stock increased.', ['ref' => $purchase->reference]));
    }

    public function show(Request $request, Purchase $purchase)
    {
        $this->authorizeShopRecord($request, $purchase->shop_id);

        return view('purchases.show', ['purchase' => $purchase->load('items.product', 'shop', 'supplier', 'user')]);
    }

    public function void(Request $request, Purchase $purchase)
    {
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];
        $this->service->void($purchase, $reason, $request->user());

        return back()->with('success', __('Purchase voided and stock removed.'));
    }
}
