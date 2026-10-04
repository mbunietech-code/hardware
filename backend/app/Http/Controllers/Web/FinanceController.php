<?php

namespace App\Http\Controllers\Web;

use App\Models\CapitalEntry;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Services\CapitalService;
use App\Services\DebtService;
use App\Services\ExpenseService;
use Illuminate\Http\Request;

class FinanceController extends WebController
{
    public function __construct(
        private ExpenseService $expenseService,
        private CapitalService $capitalService,
        private DebtService $debtService,
    ) {}

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => 'required|string|max:255'])['reason'];
    }

    public function expenses(Request $request)
    {
        $q = $this->scopeShops(Expense::with('shop:id,name', 'category:id,name', 'user:id,name'), $request);
        $q = $this->dateRange($q, $request, 'expense_date')
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', $request->integer('category_id')))
            ->where('status', $request->input('status', 'active'));

        return view('finance.expenses', [
            'expenses' => (clone $q)->latest('id')->paginate(30)->withQueryString(),
            'total' => (float) (clone $q)->sum('amount'),
            'shops' => $this->shopOptions($request),
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function storeExpense(Request $request)
    {
        $this->expenseService->create($request->all(), $request->user());

        return back()->with('success', __('Expense recorded.'));
    }

    public function voidExpense(Request $request, Expense $expense)
    {
        $this->expenseService->void($expense, $this->reason($request), $request->user());

        return back()->with('success', __('Expense voided.'));
    }

    public function capital(Request $request)
    {
        abort_unless($request->user()->hasPermission('record_capital'), 403);
        $q = $this->scopeShops(CapitalEntry::with('shop:id,name', 'user:id,name'), $request);
        $q = $this->dateRange($q, $request, 'entry_date');
        $active = CapitalEntry::where('status', 'active')
            ->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('shop_id', $request->user()->shop_id));

        return view('finance.capital', [
            'entries' => (clone $q)->latest('id')->paginate(30)->withQueryString(),
            'balance' => (float) (clone $active)->where('type', 'injection')->sum('amount') - (float) (clone $active)->where('type', 'withdrawal')->sum('amount'),
            'shops' => $this->shopOptions($request),
        ]);
    }

    public function storeCapital(Request $request)
    {
        $this->capitalService->create($request->all(), $request->user());

        return back()->with('success', __('Capital entry recorded.'));
    }

    public function voidCapital(Request $request, CapitalEntry $capital)
    {
        $this->capitalService->void($capital, $this->reason($request), $request->user());

        return back()->with('success', __('Capital entry voided.'));
    }

    public function debts(Request $request)
    {
        $q = $this->scopeShops(Debt::with('shop:id,name'), $request)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->input('status', 'outstanding') === 'outstanding', fn ($q) => $q->whereIn('status', ['open', 'partial']),
                fn ($q) => $q->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status)))
            ->when($request->filled('search'), fn ($q) => $q->where('party_name', 'like', '%'.$request->search.'%'));

        return view('finance.debts', [
            'debts' => (clone $q)->orderByRaw('due_date IS NULL, due_date')->latest('id')->paginate(30)->withQueryString(),
            'receivable' => (float) (clone $q)->where('type', 'receivable')->sum('balance'),
            'payable' => (float) (clone $q)->where('type', 'payable')->sum('balance'),
            'shops' => $this->shopOptions($request),
            'customers' => Customer::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function storeDebt(Request $request)
    {
        $debt = $this->debtService->create($request->all(), $request->user());

        return redirect()->route('debts.show', $debt)->with('success', __('Debt recorded.'));
    }

    public function showDebt(Request $request, Debt $debt)
    {
        $this->authorizeShopRecord($request, $debt->shop_id);

        return view('finance.debt', ['debt' => $debt->load('payments.user', 'shop', 'customer', 'supplier', 'user', 'sourceRecord')]);
    }

    public function payDebt(Request $request, Debt $debt)
    {
        $this->debtService->pay(['debt_id' => $debt->id] + $request->all(), $request->user());

        return back()->with('success', __('Payment recorded and balance updated.'));
    }

    public function cancelDebt(Request $request, Debt $debt)
    {
        $this->debtService->cancel($debt, $this->reason($request), $request->user());

        return back()->with('success', __('Debt cancelled.'));
    }
}
