<?php

namespace App\Http\Controllers\Web;

use App\Models\AuditLog;
use App\Models\SyncReceipt;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Http\Request;

class SystemController extends WebController
{
    public function notifications(Request $request)
    {
        return view('system.notifications', [
            'notifications' => SystemNotification::visibleTo($request->user())->with('shop:id,name')
                ->when(! $request->boolean('all'), fn ($q) => $q->whereNull('resolved_at'))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function readAll(Request $request)
    {
        SystemNotification::visibleTo($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', __('All alerts marked as read.'));
    }

    public function audit(Request $request)
    {
        abort_unless($request->user()->hasPermission('view_audit'), 403);
        $q = $this->scopeShops(AuditLog::with('user:id,name', 'shop:id,name'), $request);

        return view('system.audit', [
            'logs' => $this->dateRange($q, $request, 'created_at')
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
                ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->action.'%'))
                ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
                ->latest('id')->paginate(50)->withQueryString(),
            'shops' => $this->shopOptions($request),
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function sync(Request $request)
    {
        return view('system.sync', [
            'receipts' => SyncReceipt::with('user:id,name', 'shop:id,name')
                ->when($request->input('status', 'conflict') !== 'all', fn ($q) => $q->where('status', $request->input('status', 'conflict')))
                ->latest('id')->paginate(40)->withQueryString(),
            'counts' => SyncReceipt::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function syncShow(SyncReceipt $receipt)
    {
        return view('system.sync-show', ['receipt' => $receipt->load('user', 'shop')]);
    }

    public function resolve(Request $request, SyncReceipt $receipt, SyncService $sync)
    {
        $decision = $request->validate(['decision' => 'required|in:accept,reject'])['decision'];
        $sync->resolve($receipt, $decision, $request->user());

        return redirect()->route('sync.index')->with('success', $decision === 'accept'
            ? __('Conflict accepted: the record was saved with overrides.')
            : __('Conflict rejected. The device will show it as rejected.'));
    }
}
