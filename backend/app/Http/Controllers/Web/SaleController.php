<?php

namespace App\Http\Controllers\Web;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Support\Money;
use Illuminate\Http\Request;

class SaleController extends WebController
{
    public function __construct(private SaleService $service) {}

    public function index(Request $request)
    {
        $q = $this->scopeShops(Sale::with('shop:id,name', 'customer:id,name', 'user:id,name'), $request);

        return view('sales.index', [
            'sales' => $this->dateRange($q, $request, 'sale_date')
                ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->payment_status))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('search'), fn ($q) => $q->where('reference', 'like', '%'.$request->search.'%'))
                ->latest('id')->paginate(30)->withQueryString(),
            'shops' => $this->shopOptions($request),
        ]);
    }

    public function create(Request $request)
    {
        $shops = $this->shopOptions($request);
        $shopId = (int) ($request->input('shop_id') ?: $request->user()->shop_id ?: array_key_first($shops));
        $stock = StockBalance::where('shop_id', $shopId)->pluck('quantity', 'product_id');

        return view('sales.create', [
            'session' => DailySession::where('shop_id', $shopId)->whereDate('business_date', now()->toDateString())->first(),
            'shops' => $shops,
            'shopId' => $shopId,
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'category_id', 'code', 'name', 'unit', 'selling_price', 'cost_price'])
                ->map(fn ($p) => $p->toArray() + ['stock' => (float) ($stock[$p->id] ?? 0)]),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['items'] = collect($data['items'] ?? [])->map(fn ($i) => [
            'product_id' => $i['product_id'] ?? null, 'quantity' => $i['quantity'] ?? null,
            'unit_price' => $i['price'] ?? null, 'discount' => $i['discount'] ?? 0,
        ])->filter(fn ($i) => $i['product_id'])->values()->all();
        if (($data['amount_paid'] ?? '') === '') {
            unset($data['amount_paid']);
        }
        $sale = $this->service->create($data, $request->user());

        return redirect()->route('sales.show', $sale)
            ->with('success', __('Sale :ref saved.', ['ref' => $sale->reference]))->with('warnings', $this->service->warnings);
    }

    public function show(Request $request, Sale $sale, ReturnService $returns)
    {
        $this->authorizeShopRecord($request, $sale->shop_id);
        $sale->load('items.product', 'shop', 'customer', 'user', 'returns.items.product', 'returns.user');
        $debt = Debt::where('source_type', 'sale')->where('source_id', $sale->id)->whereIn('status', ['open', 'partial'])->first();

        return view('sales.show', [
            'sale' => $sale,
            'returnable' => $returns->returnable($sale),
            'unitValues' => $sale->items->mapWithKeys(fn ($i) => [$i->id => $returns->unitValue($sale, $i)])->all(),
            'debtBalance' => (float) ($debt?->balance ?? 0),
        ]);
    }

    public function storeReturn(Request $request, Sale $sale, ReturnService $returns)
    {
        $data = $request->all();
        $data['items'] = collect($data['items'] ?? [])->map(fn ($row, $id) => [
            'sale_item_id' => (int) $id, 'quantity' => $row['quantity'] ?? 0, 'restock' => ! empty($row['restock']),
        ])->values()->all();
        $return = $returns->create($sale, $data, $request->user());

        return back()->with('success', __('Return :ref saved. Refund to customer: :amount.', [
            'ref' => $return->reference, 'amount' => Money::format($return->refund_amount),
        ]));
    }

    public function receipt(Request $request, Sale $sale)
    {
        $this->authorizeShopRecord($request, $sale->shop_id);

        return view('sales.receipt', ['sale' => $sale->load('items.product', 'shop', 'customer', 'user')]);
    }

    public function void(Request $request, Sale $sale)
    {
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];
        $this->service->void($sale, $reason, $request->user());

        return back()->with('success', __('Sale voided and stock returned.'));
    }
}
