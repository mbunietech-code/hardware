<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\Sale;
use App\Models\SyncReceipt;
use App\Services\DailySessionService;
use App\Services\SyncService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    private function push(array $items)
    {
        Sanctum::actingAs($this->shopAdmin);

        return $this->withHeader('X-Device-Id', 'phone-1')->postJson('/api/v1/sync/push', ['items' => $items]);
    }

    private function saleItem(array $payload = [], ?string $uuid = null): array
    {
        return ['entity' => 'sale', 'local_uuid' => $uuid ?? (string) Str::uuid(), 'payload' => array_merge([
            'shop_id' => $this->main->id, 'payment_method' => 'cash', 'client_created_at' => now()->toIso8601String(),
            'items' => [['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 19000]],
        ], $payload)];
    }

    public function test_offline_batch_syncs_in_order_without_duplicates(): void
    {
        $sessionUuid = (string) Str::uuid();
        $customerUuid = (string) Str::uuid();
        $adjust = ['entity' => 'stock_adjustment', 'local_uuid' => (string) Str::uuid(), 'payload' => ['shop_id' => $this->main->id, 'product_id' => $this->p1, 'direction' => 'in', 'quantity' => 10, 'reason' => 'Opening']];
        $items = [
            ['entity' => 'daily_session_open', 'local_uuid' => $sessionUuid, 'payload' => ['shop_id' => $this->main->id, 'business_date' => now()->toDateString(), 'opening_cash' => 5000]],
            $adjust,
            ['entity' => 'customer', 'local_uuid' => $customerUuid, 'payload' => ['name' => 'Asha', 'phone' => '0755']],
            $this->saleItem(['payment_method' => 'credit', 'customer_local_uuid' => $customerUuid]),
            $this->saleItem(),
        ];

        $res = $this->push($items)->assertOk()->json();
        $this->assertSame(['accepted' => 5], $res['summary'], json_encode($res));
        $this->assertSame(2, Sale::count());
        $this->assertSame(8.0, $this->qty($this->p1));
        $this->assertSame('Asha', Debt::first()->party_name);
        $this->assertSame('sync', Sale::first()->source);
        $this->assertSame('phone-1', Sale::first()->device_id);

        // Network dropped before the device saw the response: it retries the same batch.
        $again = $this->push($items)->assertOk()->json();
        $this->assertTrue(collect($again['results'])->every(fn ($r) => $r['status'] === 'accepted' && ($r['duplicate'] ?? false)));
        $this->assertSame(2, Sale::count(), 'Retry must not double count');
        $this->assertSame(8.0, $this->qty($this->p1));
        $this->assertSame(1, Customer::count());
    }

    public function test_validation_failure_is_rejected_and_reported(): void
    {
        $this->openDay();
        $res = $this->push([$this->saleItem(['items' => []])])->json();
        $this->assertSame('rejected', $res['results'][0]['status']);
        $this->assertArrayHasKey('errors', $res['results'][0]);
    }

    public function test_conflicts_are_held_and_resolved_by_admin(): void
    {
        $this->openDay();
        $res = $this->push([$this->saleItem()])->json(); // no stock on server
        $this->assertSame('conflict', $res['results'][0]['status']);
        $this->assertSame('insufficient_stock', $res['results'][0]['error_code']);
        $this->assertDatabaseHas('system_notifications', ['type' => 'sync_conflict']);

        $receipt = SyncReceipt::first();
        app(SyncService::class)->resolve($receipt, 'accept', $this->admin);
        $this->assertSame('accepted', $receipt->fresh()->status);
        $this->assertSame(-1.0, $this->qty($this->p1));

        Sanctum::actingAs($this->shopAdmin);
        $status = $this->postJson('/api/v1/sync/status', ['uuids' => [$receipt->local_uuid]])->json('data.0');
        $this->assertSame('accepted', $status['status']);
        $this->assertSame('force_accepted', $status['resolution']);
    }

    public function test_sale_for_closed_day_is_a_conflict(): void
    {
        $session = $this->openDay();
        $this->stockUp($this->p1, 5, 1000);
        app(DailySessionService::class)->close($session, [], $this->shopAdmin);
        $res = $this->push([$this->saleItem()])->json();
        $this->assertSame('conflict', $res['results'][0]['status']);
        $this->assertSame('day_closed', $res['results'][0]['error_code']);

        app(SyncService::class)->resolve(SyncReceipt::first(), 'accept', $this->admin);
        $this->assertEquals(19000, $session->fresh()->totals['sales_total'], 'Closed day totals are recomputed');
    }

    public function test_pull_returns_catalog_and_only_own_shop_stock(): void
    {
        Sanctum::actingAs($this->shopAdmin);
        $data = $this->getJson('/api/v1/sync/pull')->assertOk()->json();
        $this->assertTrue($data['full']);
        $this->assertNotEmpty($data['products']);
        $this->assertCount(1, $data['shops']);
        $this->assertTrue(collect($data['stock'])->every(fn ($s) => $s['shop_id'] === $this->main->id));

        $later = $this->getJson('/api/v1/sync/pull?since='.urlencode(now()->addMinute()->toIso8601String()))->json();
        $this->assertEmpty($later['products']);
    }
}
