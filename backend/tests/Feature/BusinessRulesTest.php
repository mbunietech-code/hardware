<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Debt;
use App\Models\ExpenseCategory;
use App\Models\StockMovement;
use App\Services\DailySessionService;
use App\Services\DebtService;
use App\Services\ExpenseService;
use App\Services\ProfitService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessRulesTest extends TestCase
{
    private function sell(array $items, array $extra = [], $user = null)
    {
        return app(SaleService::class)->create(array_merge([
            'shop_id' => $this->main->id, 'payment_method' => 'cash', 'items' => $items,
        ], $extra), $user ?? $this->shopAdmin);
    }

    public function test_transactions_require_an_open_day(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->stockUp($this->p1, 10, 100);
    }

    public function test_purchase_increases_and_sale_decreases_stock_with_movements(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 50, 16000);
        $this->assertSame(50.0, $this->qty($this->p1));

        $sale = $this->sell([['product_id' => $this->p1, 'quantity' => 3, 'unit_price' => 19000]]);
        $this->assertSame(47.0, $this->qty($this->p1));
        $this->assertSame('57000.00', $sale->total);
        $this->assertSame('48000.00', $sale->fresh()->cost_total);

        $movements = StockMovement::where('product_id', $this->p1)->orderBy('id')->get();
        $this->assertSame(['purchase', 'sale'], $movements->pluck('type')->all());
        $this->assertEquals(50, $movements[1]->before_qty);
        $this->assertEquals(47, $movements[1]->after_qty);
        $this->assertTrue(AuditLog::where('action', 'sale.created')->exists());
    }

    public function test_cannot_sell_more_than_available_stock(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 2, 16000);
        try {
            $this->sell([['product_id' => $this->p1, 'quantity' => 5, 'unit_price' => 19000]]);
            $this->fail('Expected insufficient stock');
        } catch (BusinessRuleException $e) {
            $this->assertSame('insufficient_stock', $e->errorCode);
        }
        $this->assertSame(2.0, $this->qty($this->p1), 'Failed sale must not change stock');
    }

    public function test_negative_stock_allowed_when_configured(): void
    {
        Settings::set('negative_stock_policy', 'allow');
        $this->openDay();
        $this->sell([['product_id' => $this->p1, 'quantity' => 2, 'unit_price' => 19000]]);
        $this->assertSame(-2.0, $this->qty($this->p1));
    }

    public function test_weighted_average_cost(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 100);
        $this->stockUp($this->p1, 10, 200);
        $sale = $this->sell([['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 300]]);
        $this->assertEquals(150, $sale->items->first()->unit_cost);

        Settings::set('costing_method', 'latest_cost');
        $sale = $this->sell([['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 300]]);
        $this->assertEquals(200, $sale->items->first()->unit_cost);
    }

    public function test_credit_sale_creates_debt_and_repayment_reduces_balance(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 16000);
        $sale = $this->sell([['product_id' => $this->p1, 'quantity' => 2, 'unit_price' => 19000]], ['payment_method' => 'credit', 'amount_paid' => 8000, 'customer_name' => 'Juma']);
        $this->assertSame('partial', $sale->payment_status);

        $debt = Debt::first();
        $this->assertSame('receivable', $debt->type);
        $this->assertEquals(30000, $debt->balance);

        app(DebtService::class)->pay(['debt_id' => $debt->id, 'amount' => 10000], $this->shopAdmin);
        $this->assertEquals(20000, $debt->fresh()->balance);
        $this->assertSame('partial', $debt->fresh()->status);

        app(DebtService::class)->pay(['debt_id' => $debt->id, 'amount' => 20000], $this->shopAdmin);
        $this->assertSame('paid', $debt->fresh()->status);
        $this->assertSame('paid', $sale->fresh()->payment_status);

        $this->expectException(BusinessRuleException::class);
        app(DebtService::class)->pay(['debt_id' => $debt->id, 'amount' => 1], $this->shopAdmin);
    }

    public function test_unpaid_sale_requires_customer(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 16000);
        $this->expectException(ValidationException::class);
        $this->sell([['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 19000]], ['payment_method' => 'credit']);
    }

    public function test_void_sale_restores_stock_and_requires_permission(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 16000);
        $sale = $this->sell([['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 19000]]);

        try {
            app(SaleService::class)->void($sale, 'mistake', $this->shopAdmin);
            $this->fail('Shop admin without permission must not void');
        } catch (AuthorizationException) {
        }

        app(SaleService::class)->void($sale, 'mistake', $this->admin);
        $this->assertSame(10.0, $this->qty($this->p1));
        $this->assertSame('voided', $sale->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'sale.voided')->exists());
    }

    public function test_shop_admin_cannot_record_for_other_shop(): void
    {
        $this->openDay($this->branch, $this->admin);
        $this->expectException(AuthorizationException::class);
        $this->sell([['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 1]], ['shop_id' => $this->branch->id]);
    }

    public function test_stock_adjustment_records_before_and_after(): void
    {
        $adj = app(StockService::class)->adjust(['shop_id' => $this->main->id, 'product_id' => $this->p2, 'counted_quantity' => 12, 'reason' => 'Opening stock'], $this->shopAdmin);
        $this->assertEquals(0, $adj->before_qty);
        $this->assertEquals(12, $adj->after_qty);
        $this->assertTrue(AuditLog::where('action', 'stock.adjusted')->exists());
    }

    public function test_daily_closing_computes_expected_cash(): void
    {
        $session = $this->openDay();
        $this->stockUp($this->p1, 10, 1000); // cash out 10,000
        $this->sell([['product_id' => $this->p1, 'quantity' => 2, 'unit_price' => 5000]]); // cash in 10,000
        app(ExpenseService::class)->create(['shop_id' => $this->main->id, 'expense_category_id' => $this->rent, 'amount' => 2500, 'reason' => 'Lunch'], $this->shopAdmin);

        $closed = app(DailySessionService::class)->close($session, ['closing_cash' => 7000, 'closing_notes' => 'ok'], $this->shopAdmin);
        // 10,000 opening + 10,000 sales − 10,000 purchases − 2,500 expenses
        $this->assertEquals(7500, $closed->expected_cash);
        $this->assertEquals(-500, $closed->cash_difference);
        $this->assertSame('closed', $closed->status);
        $this->assertEquals(10000, $closed->totals['sales_total']);

        $this->expectException(BusinessRuleException::class);
        $this->sell([['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 5000]]);
    }

    public function test_profit_and_allocation(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 1000);
        $this->sell([['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 3000]]); // revenue 12,000 cost 4,000
        app(ExpenseService::class)->create(['shop_id' => $this->main->id, 'expense_category_id' => $this->rent, 'amount' => 3000, 'reason' => 'Rent'], $this->shopAdmin);
        $ownerDrawings = ExpenseCategory::where('name', 'Owner drawings')->first();
        app(ExpenseService::class)->create(['shop_id' => $this->main->id, 'expense_category_id' => $ownerDrawings->id, 'amount' => 1000, 'reason' => 'x'], $this->shopAdmin);

        $s = app(ProfitService::class)->summary(now()->toDateString(), now()->toDateString());
        $this->assertEquals(8000, $s['gross_profit']);
        $this->assertEquals(5000, $s['net_profit']); // owner drawings do not reduce profit

        $a = app(ProfitService::class)->generateAllocation(['period_start' => now()->toDateString(), 'period_end' => now()->toDateString()], $this->admin);
        $this->assertEquals(3000, $a->primary_amount);
        $this->assertEquals(2000, $a->secondary_amount);
        app(ProfitService::class)->setAllocationStatus($a, 'approved', $this->admin);

        $this->expectException(BusinessRuleException::class);
        app(ProfitService::class)->generateAllocation(['period_start' => now()->toDateString(), 'period_end' => now()->toDateString()], $this->admin);
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::create(['action' => 'x']);
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'y']);
    }

    public function test_low_stock_notification(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 25, 1000); // reorder level 20
        $this->sell([['product_id' => $this->p1, 'quantity' => 6, 'unit_price' => 2000]]);
        $this->assertDatabaseHas('system_notifications', ['type' => 'low_stock', 'shop_id' => $this->main->id, 'resolved_at' => null]);
        $this->stockUp($this->p1, 10, 1000);
        $this->assertDatabaseMissing('system_notifications', ['type' => 'low_stock', 'resolved_at' => null]);
    }
}
