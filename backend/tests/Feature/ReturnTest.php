<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Debt;
use App\Services\DailySessionService;
use App\Services\ProfitService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    private function sale(array $extra = [])
    {
        return app(SaleService::class)->create(array_merge([
            'shop_id' => $this->main->id, 'payment_method' => 'cash',
            'items' => [['product_id' => $this->p1, 'quantity' => 4, 'unit_price' => 20000]],
        ], $extra), $this->admin);
    }

    private function giveReturnPermission(): void
    {
        $this->shopAdmin->update(['permissions' => ($this->shopAdmin->permissions ?? []) + ['process_returns' => true]]);
    }

    public function test_cash_return_restocks_refunds_and_lowers_profit_and_cash(): void
    {
        $session = $this->openDay();
        $this->stockUp($this->p1, 10, 15000);
        $sale = $this->sale();
        $item = $sale->items->first();
        $before = app(ProfitService::class)->summary(now()->toDateString(), now()->toDateString());

        $this->giveReturnPermission();
        $ret = app(ReturnService::class)->create($sale, ['reason' => 'Wrong size', 'items' => [['sale_item_id' => $item->id, 'quantity' => 1, 'restock' => true]]], $this->shopAdmin);

        $this->assertEquals(20000, $ret->return_value);
        $this->assertEquals(20000, $ret->refund_amount);
        $this->assertSame(7.0, $this->qty($this->p1));
        $this->assertEquals(20000, $sale->fresh()->returned_total);

        $after = app(ProfitService::class)->summary(now()->toDateString(), now()->toDateString());
        $this->assertEquals($before['revenue'] - 20000, $after['revenue']);
        $this->assertEquals($before['cost_of_goods'] - 15000, $after['cost_of_goods']);

        $t = app(DailySessionService::class)->computeTotals($session);
        $this->assertEquals(20000, $t['refunds_paid']);
        // opening 10,000 − purchase 150,000 + sale 80,000 − refund 20,000
        $this->assertEquals(10000 - 150000 + 80000 - 20000, $t['expected_cash']);
    }

    public function test_cannot_return_more_than_sold_and_damaged_goods_stay_out_of_stock(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 15000);
        $sale = $this->sale();
        $item = $sale->items->first();
        $svc = app(ReturnService::class);

        $svc->create($sale, ['reason' => 'Broken', 'items' => [['sale_item_id' => $item->id, 'quantity' => 3, 'restock' => false]]], $this->admin);
        $this->assertSame(6.0, $this->qty($this->p1), 'Damaged goods are not restocked');

        $this->expectException(ValidationException::class);
        $svc->create($sale, ['reason' => 'Again', 'items' => [['sale_item_id' => $item->id, 'quantity' => 2]]], $this->admin);
    }

    public function test_credit_sale_return_reduces_debt_first(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 15000);
        $sale = $this->sale(['payment_method' => 'credit', 'amount_paid' => 50000, 'customer_name' => 'Juma']);
        $debt = Debt::first();
        $this->assertEquals(30000, $debt->balance);

        $ret = app(ReturnService::class)->create($sale, ['reason' => 'Extra', 'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 2]]], $this->admin);
        $this->assertEquals(40000, $ret->return_value);
        $this->assertEquals(30000, $ret->debt_reduction);
        $this->assertEquals(10000, $ret->refund_amount);
        $this->assertEquals(0, $debt->fresh()->balance);
        $this->assertEquals(0, $sale->fresh()->balance);
    }

    public function test_permission_and_void_blocked_after_return(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 15000);
        $sale = $this->sale();
        $this->shopAdmin->update(['permissions' => ['process_returns' => false]]);
        try {
            app(ReturnService::class)->create($sale, ['reason' => 'x', 'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1]]], $this->shopAdmin);
            $this->fail('Shop admin without permission must not process returns');
        } catch (AuthorizationException) {
        }

        app(ReturnService::class)->create($sale, ['reason' => 'x', 'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1]]], $this->admin);
        $this->expectException(BusinessRuleException::class);
        app(SaleService::class)->void($sale, 'mistake', $this->admin);
    }

    public function test_web_return_flow_and_returns_report(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 15000);
        $sale = $this->sale();
        $item = $sale->items->first();

        $this->actingAs($this->admin)->get("/sales/{$sale->id}")->assertOk()->assertSee('Return items');
        $this->from("/sales/{$sale->id}")->post("/sales/{$sale->id}/returns", [
            'reason' => 'Changed mind', 'refund_method' => 'cash',
            'items' => [$item->id => ['quantity' => 2, 'restock' => '1']],
        ])->assertRedirect("/sales/{$sale->id}")->assertSessionHas('success');
        $this->assertSame(8.0, $this->qty($this->p1));
        $this->get('/reports/returns')->assertOk()->assertSee('Changed mind');
        $this->get("/sales/{$sale->id}")->assertSee('R-MAIN-');
    }
}
