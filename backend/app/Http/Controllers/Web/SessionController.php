<?php

namespace App\Http\Controllers\Web;

use App\Models\DailySession;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\DailySessionService;
use Illuminate\Http\Request;

class SessionController extends WebController
{
    public function __construct(private DailySessionService $service) {}

    public function index(Request $request)
    {
        $q = $this->scopeShops(DailySession::with('shop:id,name', 'opener:id,name', 'closer:id,name'), $request);

        return view('sessions.index', [
            'sessions' => $this->dateRange($q, $request, 'business_date')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->latest('business_date')->paginate(30)->withQueryString(),
            'shops' => $this->shopOptions($request),
        ]);
    }

    public function open(Request $request)
    {
        $session = $this->service->open($request->all(), $request->user());

        return redirect()->route('sessions.show', $session)->with('success', __('Business day opened.'));
    }

    public function show(Request $request, DailySession $session)
    {
        $this->authorizeShopRecord($request, $session->shop_id);
        $sid = $session->id;

        return view('sessions.show', [
            'session' => $session->load('shop', 'opener', 'closer'),
            'totals' => $session->isOpen() ? $this->service->computeTotals($session) : $session->totals,
            'sales' => Sale::with('customer:id,name')->where('daily_session_id', $sid)->latest('id')->get(),
            'purchases' => Purchase::with('supplier:id,name')->where('daily_session_id', $sid)->latest('id')->get(),
            'expenses' => Expense::with('category:id,name')->where('daily_session_id', $sid)->latest('id')->get(),
            'payments' => DebtPayment::with('debt:id,party_name,type')->where('daily_session_id', $sid)->latest('id')->get(),
        ]);
    }

    public function close(Request $request, DailySession $session)
    {
        $this->service->close($session, $request->all(), $request->user());

        return redirect()->route('sessions.show', $session)->with('success', __('Business day closed.'));
    }

    public function reopen(Request $request, DailySession $session)
    {
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];
        $this->service->reopen($session, $reason, $request->user());

        return redirect()->route('sessions.show', $session)->with('success', __('Business day reopened. Remember to close it again.'));
    }
}
