<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    public function test_offline_sale_return_syncs_by_product_and_pull_has_profit_summary(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 10, 15000);
        $this->shopAdmin->update(['permissions' => ['process_returns' => true]]);
        Sanctum::actingAs($this->shopAdmin);
        $saleUuid = (string) Str::uuid();
        $items = [
            ['entity' => 'sale', 'local_uuid' => $saleUuid, 'payload' => ['shop_id' => $this->main->id, 'payment_method' => 'cash',
                'items' => [['product_id' => $this->p1, 'quantity' => 3, 'unit_price' => 20000]]]],
            ['entity' => 'sale_return', 'local_uuid' => (string) Str::uuid(), 'payload' => ['sale_local_uuid' => $saleUuid, 'reason' => 'Wrong item',
                'refund_method' => 'cash', 'items' => [['product_id' => $this->p1, 'quantity' => 1, 'restock' => true]]]],
        ];
        $res = $this->postJson('/api/v1/sync/push', ['items' => $items])->assertOk()->json();
        $this->assertSame(['accepted' => 2], $res['summary'], json_encode($res));
        $this->assertSame(8.0, $this->qty($this->p1));

        $pull = $this->getJson('/api/v1/sync/pull')->json('summary');
        $this->assertSame([60, 40], $pull['percents']);
        $today = $pull['periods']['daily'];
        // 2 kept × (20,000 − 15,000) = 10,000 profit
        $this->assertEquals(10000, $today['profit']);
        $this->assertEquals(6000, $today['primary']);
    }

    public function test_change_password_via_api_clears_flag(): void
    {
        $this->shopAdmin->update(['must_change_password' => true]);
        $token = $this->postJson('/api/v1/auth/login', ['login' => 'shop@hardware.test', 'password' => 'password'])
            ->assertJsonPath('user.must_change_password', true)->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertStatus(422);
        $this->withToken($token)->postJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'Duka2026!', 'password_confirmation' => 'Duka2026!'])
            ->assertOk()->assertJsonPath('user.must_change_password', false);
    }
}
