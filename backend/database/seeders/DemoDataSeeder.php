<?php

namespace Database\Seeders;

use App\Models\Debt;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\DailySessionService;
use App\Services\DebtService;
use App\Services\ExpenseService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Support\ActionContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Optional sample activity for demos: `php artisan db:seed --class=DemoDataSeeder`.
 * Creates two weeks of opened/closed days with purchases, sales, expenses and debts.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        ActionContext::current()->source = 'system';
        $sessions = app(DailySessionService::class);
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->first();
        $products = Product::all();
        $base = Carbon::today();
        $rent = ExpenseCategory::where('name', 'Transport')->first();

        foreach (Shop::where('is_active', true)->get() as $shop) {
            $user = User::where('shop_id', $shop->id)->first() ?? $admin;
            for ($d = 13; $d >= 0; $d--) {
                $date = $base->copy()->subDays($d)->toDateString();
                Carbon::setTestNow(Carbon::parse($date)->setTime(8, 0));
                $session = $sessions->open(['shop_id' => $shop->id, 'business_date' => $date, 'opening_cash' => 50000, 'opening_notes' => null], $user);

                if ($d % 5 === 0 || $d === 13) {
                    app(PurchaseService::class)->create([
                        'shop_id' => $shop->id, 'purchase_date' => $date, 'payment_method' => $d === 13 ? 'cash' : 'credit',
                        'amount_paid' => $d === 13 ? null : 100000, 'supplier_name' => 'Bamburi Distributors',
                        'items' => $products->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 40, 'unit_cost' => $p->cost_price])->all(),
                    ], $admin);
                }

                foreach (range(1, 3 + ($d % 4)) as $i) {
                    $picked = $products->random(rand(1, 3));
                    $credit = $i === 2 && $d % 3 === 0;
                    app(SaleService::class)->create([
                        'shop_id' => $shop->id, 'sale_date' => $date,
                        'payment_method' => $credit ? 'credit' : collect(['cash', 'cash', 'mobile_money'])->random(),
                        'customer_name' => $credit ? collect(['Juma Fundi', 'Mama Asha', 'Kilimo Contractors'])->random() : null,
                        'items' => $picked->map(fn ($p) => ['product_id' => $p->id, 'quantity' => rand(1, 4), 'unit_price' => $p->selling_price])->values()->all(),
                    ], $user, ['allow_negative' => true]);
                }

                app(ExpenseService::class)->create(['shop_id' => $shop->id, 'expense_category_id' => $rent->id, 'expense_date' => $date,
                    'amount' => rand(3, 15) * 1000, 'reason' => 'Delivery to site'], $user);

                if ($d > 0) {
                    Carbon::setTestNow(Carbon::parse($date)->setTime(19, 0));
                    $sessions->close($session, ['closing_cash' => max(0, $sessions->computeTotals($session)['expected_cash'] - ($d % 4 === 0 ? 2000 : 0)),
                        'closing_notes' => 'Closed normally'], $user);
                }
            }
            Carbon::setTestNow();
        }

        $debt = Debt::where('type', 'receivable')->first();
        if ($debt) {
            app(DebtService::class)->pay(['debt_id' => $debt->id, 'amount' => min(10000, (float) $debt->balance)], $admin);
        }
    }
}
