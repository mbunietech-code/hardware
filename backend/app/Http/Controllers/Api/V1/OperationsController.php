<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\DailySession;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\SystemNotification;
use App\Services\DailySessionService;
use App\Services\ReportService;
use App\Services\StockService;
use App\Services\SyncService;
use Illuminate\Http\Request;

class OperationsController extends ApiController
{
    public function __construct(
        private StockService $stock,
        private DailySessionService $sessions,
        private SyncService $sync,
        private ReportService $reports,
    ) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $sales = $this->scopeShops(Sale::where('status', 'completed')->whereDate('sale_date', $today), $request);
        $lowStock = $this->scopeShops(StockBalance::query(), $request)
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->where('products.reorder_level', '>', 0)->whereColumn('stock_balances.quantity', '<=', 'products.reorder_level')->count();

        return response()->json(['data' => [
            'date' => $today,
            'sales_today' => (float) (clone $sales)->sum('total'),
            'sales_count_today' => (clone $sales)->count(),
            'low_stock_count' => $lowStock,
            'unread_notifications' => SystemNotification::visibleTo($user)->whereNull('read_at')->whereNull('resolved_at')->count(),
            'session' => $user->shop_id ? DailySession::where('shop_id', $user->shop_id)->whereDate('business_date', $today)->first() : null,
        ]]);
    }

    public function stock(Request $request)
    {
        return $this->scopeShops(StockBalance::with('product:id,code,name,unit,reorder_level,selling_price', 'shop:id,name'), $request)
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->paginate($this->perPage($request));
    }

    public function adjustStock(Request $request)
    {
        $data = $request->all() + ['shop_id' => $request->user()->shop_id];

        return response()->json(['data' => $this->stock->adjust($data, $request->user())], 201);
    }

    public function movements(Request $request)
    {
        $q = $this->scopeShops(StockMovement::with('product:id,name,code', 'user:id,name'), $request);

        return $this->dateRange($q, $request, 'created_at')
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->latest('id')->paginate($this->perPage($request));
    }

    public function sessions(Request $request)
    {
        $q = $this->scopeShops(DailySession::with('opener:id,name', 'closer:id,name'), $request);

        return $this->dateRange($q, $request, 'business_date')->latest('business_date')->paginate($this->perPage($request));
    }

    public function currentSession(Request $request)
    {
        $shopId = $request->user()->isSuperAdmin() ? $request->integer('shop_id') : $request->user()->shop_id;
        $session = DailySession::where('shop_id', $shopId)->whereDate('business_date', $request->date('date') ?? now())->first();

        return response()->json(['data' => $session, 'live_totals' => $session ? $this->sessions->computeTotals($session) : null]);
    }

    public function openSession(Request $request)
    {
        $data = $request->all() + ['shop_id' => $request->user()->shop_id];

        return response()->json(['data' => $this->sessions->open($data, $request->user())], 201);
    }

    public function closeSession(Request $request, DailySession $session)
    {
        return response()->json(['data' => $this->sessions->close($session, $request->all(), $request->user())]);
    }

    public function syncPush(Request $request)
    {
        $data = $request->validate(['items' => 'present|array|max:500', 'items.*.entity' => 'required|string', 'items.*.local_uuid' => 'required|string', 'items.*.payload' => 'present|array']);

        return response()->json($this->sync->push($data['items'], $request->user(), $request->header('X-Device-Id')));
    }

    public function syncPull(Request $request)
    {
        return response()->json($this->sync->pull($request->user(), $request->query('since')));
    }

    public function syncStatus(Request $request)
    {
        $data = $request->validate(['uuids' => 'present|array', 'uuids.*' => 'string']);

        return response()->json(['data' => $this->sync->status($data['uuids'])]);
    }

    public function report(Request $request, string $type)
    {
        $report = $this->reports->run($type, $request->query(), $request->user());
        if ($request->query('format') === 'csv') {
            return $this->reports->csv($report);
        }

        return response()->json($report);
    }

    public function notifications(Request $request)
    {
        return SystemNotification::visibleTo($request->user())
            ->when(! $request->boolean('all'), fn ($q) => $q->whereNull('resolved_at'))
            ->latest()->paginate($this->perPage($request))
            ->through(fn (SystemNotification $n) => $n->toArray() + ['title_text' => $n->titleText(), 'message_text' => $n->messageText()]);
    }

    public function readNotification(Request $request, SystemNotification $notification)
    {
        abort_unless(SystemNotification::visibleTo($request->user())->whereKey($notification->id)->exists(), 404);
        $notification->update(['read_at' => now()]);

        return response()->json(['data' => $notification]);
    }

    public function auditLogs(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('view_audit'), 403, __('You are not allowed to view the audit trail.'));
        $q = $this->scopeShops(AuditLog::with('user:id,name'), $request);

        return $this->dateRange($q, $request, 'created_at')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->action.'%'))
            ->latest('id')->paginate($this->perPage($request));
    }
}
