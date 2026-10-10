<?php

namespace App\Http\Controllers\Web;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProfitAllocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProfitService;
use App\Services\ReportService;
use App\Support\Settings;
use Illuminate\Http\Request;

class ReportController extends WebController
{
    public function __construct(private ReportService $reports, private ProfitService $profit) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('view_reports'), 403);

        return view('reports.index', ['types' => collect(ReportService::TYPES)
            ->reject(fn ($l, $k) => ($k === 'allocations' && ! $request->user()->isSuperAdmin()) || ($k === 'audit' && ! $request->user()->hasPermission('view_audit')))]);
    }

    public function show(Request $request, string $type)
    {
        $report = $this->reports->run($type, $request->query(), $request->user());

        return match ($request->query('export')) {
            'csv' => $this->reports->csv($report),
            'xlsx' => $this->reports->xlsx($report),
            'pdf' => $this->reports->pdf($report),
            default => $this->renderReport($request, $report),
        };
    }

    private function renderReport(Request $request, array $report)
    {

        return view('reports.show', [
            'report' => $report,
            'shops' => $this->shopOptions($request),
            'options' => [
                'users' => User::orderBy('name')->pluck('name', 'id'),
                'products' => Product::orderBy('name')->pluck('name', 'id'),
                'categories' => Category::orderBy('name')->pluck('name', 'id'),
                'expense_categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
                'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
                'movement_types' => StockMovement::TYPES,
            ],
        ]);
    }

    public function allocations(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return view('reports.allocations', [
            'allocations' => ProfitAllocation::with('shop:id,name', 'creator:id,name', 'approver:id,name')->latest('id')->paginate(20),
            'preview' => $this->profit->summary($from, $to, $request->integer('shop_id') ?: null),
            'overview' => $this->profit->allocationOverview($request->integer('shop_id') ?: null),
            'percents' => Settings::allocationPercents(),
            'labels' => [Settings::get('allocation_primary_label'), Settings::get('allocation_secondary_label')],
            'shops' => $this->shopOptions($request),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function generateAllocation(Request $request)
    {
        $this->profit->generateAllocation($request->all(), $request->user());

        return back()->with('success', __('Draft allocation created. Review and approve it.'));
    }

    public function allocationStatus(Request $request, ProfitAllocation $allocation, string $status)
    {
        $this->profit->setAllocationStatus($allocation, $status, $request->user());

        return back()->with('success', __('Allocation :status.', ['status' => __($status)]));
    }
}
