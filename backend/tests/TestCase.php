<?php

namespace Tests;

use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DailySessionService;
use App\Services\PurchaseService;
use App\Services\StockService;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $shopAdmin;

    protected User $branchAdmin;

    protected Shop $main;

    protected Shop $branch;

    protected int $p1;

    protected int $p2;

    protected int $rent;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@hardware.test')->first();
        $this->shopAdmin = User::where('email', 'shop@hardware.test')->first();
        $this->branchAdmin = User::where('email', 'branch@hardware.test')->first();
        $this->main = Shop::where('code', 'MAIN')->first();
        $this->branch = Shop::where('code', 'BR2')->first();
        $this->p1 = Product::where('code', 'CEM-50')->value('id');
        $this->p2 = Product::where('code', 'RB-12')->value('id');
        $this->rent = ExpenseCategory::where('name', 'Rent')->value('id');
    }

    protected function openDay(?Shop $shop = null, ?User $user = null, ?string $date = null)
    {
        return app(DailySessionService::class)->open([
            'shop_id' => ($shop ?? $this->main)->id, 'business_date' => $date ?? now()->toDateString(), 'opening_cash' => 10000,
        ], $user ?? $this->shopAdmin);
    }

    protected function stockUp(int $productId, float $qty, float $cost, ?Shop $shop = null)
    {
        return app(PurchaseService::class)->create([
            'shop_id' => ($shop ?? $this->main)->id, 'payment_method' => 'cash',
            'items' => [['product_id' => $productId, 'quantity' => $qty, 'unit_cost' => $cost]],
        ], $this->shopAdmin->shop_id === ($shop ?? $this->main)->id ? $this->shopAdmin : $this->admin);
    }

    protected function qty(int $productId, ?Shop $shop = null): float
    {
        return app(StockService::class)->available(($shop ?? $this->main)->id, $productId);
    }
}
