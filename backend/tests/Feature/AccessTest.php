<?php

namespace Tests\Feature;

use App\Models\DailySession;
use App\Models\Debt;
use App\Models\Device;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\CapitalService;
use App\Services\ExpenseService;
use App\Services\ReportService;
use App\Services\SaleService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessTest extends TestCase
{
    private function seedActivity(): void
    {
        $this->openDay();
        $this->stockUp($this->p1, 30, 16000);
        app(SaleService::class)->create(['shop_id' => $this->main->id, 'payment_method' => 'credit', 'customer_name' => 'Juma',
            'items' => [['product_id' => $this->p1, 'quantity' => 2, 'unit_price' => 19000]]], $this->shopAdmin);
        app(ExpenseService::class)->create(['shop_id' => $this->main->id, 'expense_category_id' => $this->rent, 'amount' => 500, 'reason' => 'Tea'], $this->shopAdmin);
        app(CapitalService::class)->create(['shop_id' => $this->main->id, 'type' => 'injection', 'amount' => 1000000, 'reason' => 'Start'], $this->admin);
    }

    public function test_every_web_page_renders_for_super_admin(): void
    {
        $this->seedActivity();
        $this->actingAs($this->admin);
        $sale = Sale::first()->id;
        $session = DailySession::first()->id;
        $purchase = Purchase::first()->id;
        $debt = Debt::first()->id;
        $pages = ['/', '/sessions', "/sessions/$session", '/sales', '/sales/create', "/sales/$sale", "/sales/$sale/receipt", '/purchases', '/purchases/create', "/purchases/$purchase",
            '/expenses', '/capital', '/debts', "/debts/$debt", '/products', '/products/create', "/products/{$this->p1}", "/products/{$this->p1}/edit", '/categories',
            '/customers', '/suppliers', '/stock', '/stock-movements', '/reports', '/notifications', '/audit', '/allocations', '/shops',
            '/users', '/users/create', "/users/{$this->shopAdmin->id}/edit", '/expense-categories', '/settings', '/devices', '/sync', '/profile'];
        foreach (array_keys(ReportService::TYPES) as $type) {
            $pages[] = "/reports/$type";
        }
        foreach ($pages as $page) {
            $this->assertSame(200, $this->get($page)->status(), "Page $page failed");
        }
        $this->get('/reports/sales?export=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_shop_admin_pages_and_restrictions(): void
    {
        $this->seedActivity();
        $this->actingAs($this->shopAdmin);
        foreach (['/', '/sessions', '/sales', '/sales/create', '/purchases/create', '/expenses', '/debts', '/products', '/stock', '/reports', '/reports/sales'] as $page) {
            $this->get($page)->assertOk();
        }
        foreach (['/users', '/shops', '/settings', '/sync', '/allocations', '/capital', '/audit', '/categories'] as $page) {
            $this->get($page)->assertForbidden();
        }

        $this->actingAs($this->branchAdmin);
        $this->get('/sales/'.Sale::first()->id)->assertForbidden();
        $this->get('/reports/sales')->assertOk()->assertDontSee('S-MAIN-');
    }

    public function test_web_login_and_deactivated_user(): void
    {
        $this->post('/login', ['login' => 'shop@hardware.test', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => '0711111111', 'password' => 'password'])->assertRedirect('/');
        $this->shopAdmin->update(['is_active' => false]);
        $this->app['auth']->forgetGuards(); // a new request reloads the user from the database
        $this->get('/')->assertRedirect('/login');
    }

    public function test_api_auth_rules(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();

        $token = $this->postJson('/api/v1/auth/login', ['login' => 'shop@hardware.test', 'password' => 'password', 'device_id' => 'abc', 'device_name' => 'Tecno'])
            ->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.shop_id', $this->main->id);
        $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();

        Device::where('device_id', 'abc')->update(['is_revoked' => true]);
        $this->withToken($token)->withHeader('X-Device-Id', 'abc')->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['login' => 'shop@hardware.test', 'password' => 'password', 'device_id' => 'abc'])->assertUnprocessable();

        $this->shopAdmin->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['login' => 'shop@hardware.test', 'password' => 'password'])->assertUnprocessable();
    }

    public function test_api_transaction_flow_and_business_errors(): void
    {
        Sanctum::actingAs($this->shopAdmin);
        $this->postJson('/api/v1/sales', ['shop_id' => $this->main->id, 'payment_method' => 'cash', 'items' => [['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 1]]])
            ->assertStatus(422)->assertJsonPath('code', 'no_open_day');
        $this->postJson('/api/v1/daily-sessions/open', ['opening_cash' => 0])->assertCreated();
        $this->postJson('/api/v1/sales', ['shop_id' => $this->main->id, 'payment_method' => 'cash', 'items' => [['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 1]]])
            ->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');
        $this->postJson('/api/v1/sales', ['shop_id' => $this->branch->id, 'payment_method' => 'cash', 'items' => [['product_id' => $this->p1, 'quantity' => 1, 'unit_price' => 1]]])
            ->assertForbidden();
        $this->getJson('/api/v1/reports/profit')->assertOk()->assertJsonStructure(['summary', 'rows', 'notice']);
    }

    public function test_web_sale_without_open_day_shows_message_not_error_page(): void
    {
        $this->actingAs($this->shopAdmin);
        $this->get('/sales/create')->assertOk()->assertSee('Open day');

        $this->from('/sales/create')->post('/sales', [
            'shop_id' => $this->main->id, 'payment_method' => 'cash',
            'items' => [['product_id' => $this->p1, 'quantity' => 1, 'price' => 1000]],
        ])->assertRedirect('/sales/create')->assertSessionHasErrors('business');

        $this->post('/sessions', ['shop_id' => $this->main->id, 'opening_cash' => 0, 'redirect' => url('/sales/create')])
            ->assertRedirect(url('/sales/create'));
        $this->get('/sales/create')->assertOk()->assertDontSee('Today is not open yet for this shop.');
    }
}
