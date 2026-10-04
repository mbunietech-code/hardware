<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CapitalEntry;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\CapitalService;
use App\Services\DebtService;
use App\Services\ExpenseService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function __construct(
        private SaleService $sales,
        private PurchaseService $purchases,
        private ExpenseService $expenses,
        private CapitalService $capital,
        private DebtService $debts,
    ) {}

    private function voidReason(Request $request): string
    {
        return $request->validate(['reason' => 'required|string|max:255'])['reason'];
    }

    // Sales
    public function sales(Request $request)
    {
        $q = $this->scopeShops(Sale::with('customer:id,name', 'user:id,name'), $request);

        return $this->dateRange($q, $request, 'sale_date')
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->payment_status))
            ->latest('id')->paginate($this->perPage($request));
    }

    public function storeSale(Request $request)
    {
        $sale = $this->sales->create($request->all(), $request->user());

        return response()->json(['data' => $sale->load('items.product:id,name,code'), 'warnings' => $this->sales->warnings], 201);
    }

    public function showSale(Request $request, Sale $sale)
    {
        $this->authorizeShopRecord($request, $sale->shop_id);

        return response()->json(['data' => $sale->load('items.product:id,name,code,unit', 'customer', 'user:id,name', 'shop')]);
    }

    public function voidSale(Request $request, Sale $sale)
    {
        return response()->json(['data' => $this->sales->void($sale, $this->voidReason($request), $request->user())]);
    }

    // Purchases
    public function purchases(Request $request)
    {
        $q = $this->scopeShops(Purchase::with('supplier:id,name', 'user:id,name'), $request);

        return $this->dateRange($q, $request, 'purchase_date')->latest('id')->paginate($this->perPage($request));
    }

    public function storePurchase(Request $request)
    {
        return response()->json(['data' => $this->purchases->create($request->all(), $request->user())->load('items.product:id,name,code')], 201);
    }

    public function showPurchase(Request $request, Purchase $purchase)
    {
        $this->authorizeShopRecord($request, $purchase->shop_id);

        return response()->json(['data' => $purchase->load('items.product:id,name,code,unit', 'supplier', 'user:id,name', 'shop')]);
    }

    public function voidPurchase(Request $request, Purchase $purchase)
    {
        return response()->json(['data' => $this->purchases->void($purchase, $this->voidReason($request), $request->user())]);
    }

    // Expenses
    public function expenses(Request $request)
    {
        $q = $this->scopeShops(Expense::with('category:id,name', 'user:id,name'), $request);

        return $this->dateRange($q, $request, 'expense_date')->latest('id')->paginate($this->perPage($request));
    }

    public function storeExpense(Request $request)
    {
        return response()->json(['data' => $this->expenses->create($request->all(), $request->user())], 201);
    }

    public function voidExpense(Request $request, Expense $expense)
    {
        return response()->json(['data' => $this->expenses->void($expense, $this->voidReason($request), $request->user())]);
    }

    // Capital
    public function capital(Request $request)
    {
        $q = $this->scopeShops(CapitalEntry::with('user:id,name'), $request);

        return $this->dateRange($q, $request, 'entry_date')->latest('id')->paginate($this->perPage($request));
    }

    public function storeCapital(Request $request)
    {
        return response()->json(['data' => $this->capital->create($request->all(), $request->user())], 201);
    }

    public function voidCapital(Request $request, CapitalEntry $capital)
    {
        return response()->json(['data' => $this->capital->void($capital, $this->voidReason($request), $request->user())]);
    }

    // Debts
    public function debts(Request $request)
    {
        return $this->scopeShops(Debt::query(), $request)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('party_name', 'like', '%'.$request->search.'%'))
            ->latest('id')->paginate($this->perPage($request));
    }

    public function storeDebt(Request $request)
    {
        return response()->json(['data' => $this->debts->create($request->all(), $request->user())], 201);
    }

    public function showDebt(Request $request, Debt $debt)
    {
        $this->authorizeShopRecord($request, $debt->shop_id);

        return response()->json(['data' => $debt->load('payments.user:id,name', 'customer', 'supplier')]);
    }

    public function payDebt(Request $request, Debt $debt)
    {
        $payment = $this->debts->pay(['debt_id' => $debt->id] + $request->all(), $request->user());

        return response()->json(['data' => $payment], 201);
    }
}
