<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::firstOrCreate(['name' => 'Hardware Business'], ['currency' => 'TZS']);

        $main = Shop::firstOrCreate(['code' => 'MAIN'], ['business_id' => $business->id, 'name' => 'Main Shop', 'location' => 'Dar es Salaam']);
        $branch = Shop::firstOrCreate(['code' => 'BR2'], ['business_id' => $business->id, 'name' => 'Branch 2', 'location' => 'Morogoro']);

        User::firstOrCreate(['email' => 'admin@hardware.test'], [
            'business_id' => $business->id, 'role' => User::ROLE_SUPER_ADMIN, 'name' => 'Super Admin',
            'phone' => '0700000000', 'password' => 'password', 'must_change_password' => true, 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'shop@hardware.test'], [
            'business_id' => $business->id, 'shop_id' => $main->id, 'role' => User::ROLE_SHOP_ADMIN, 'name' => 'Main Shop Admin',
            'phone' => '0711111111', 'password' => 'password', 'must_change_password' => true, 'is_active' => true,
            'permissions' => ['view_reports' => true, 'adjust_stock' => true, 'record_capital' => false, 'manage_products' => false, 'process_returns' => true],
        ]);
        User::firstOrCreate(['email' => 'branch@hardware.test'], [
            'business_id' => $business->id, 'shop_id' => $branch->id, 'role' => User::ROLE_SHOP_ADMIN, 'name' => 'Branch Admin',
            'phone' => '0722222222', 'password' => 'password', 'must_change_password' => true, 'is_active' => true,
            'permissions' => ['view_reports' => true],
        ]);

        foreach ([['Rent', true], ['Electricity', true], ['Transport', true], ['Salaries', true], ['Owner drawings', false], ['Other', true]] as [$name, $reduces]) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['reduces_profit' => $reduces]);
        }

        $catalog = [
            'Cement' => [['CEM-50', 'Cement 50kg bag', 'bag', 16500, 19000, 20]],
            'Steel' => [['RB-12', 'Rebar Y12 (12m)', 'pcs', 21000, 25000, 30], ['RB-16', 'Rebar Y16 (12m)', 'pcs', 36000, 42000, 20], ['BW-1', 'Binding wire 1kg', 'kg', 4000, 5500, 10]],
            'Roofing' => [['IRS-28', 'Iron sheet G28 3m', 'pcs', 24000, 28500, 25], ['NAIL-RF', 'Roofing nails 1kg', 'kg', 4500, 6000, 10]],
            'Plumbing' => [['PVC-1', 'PVC pipe 1 inch', 'pcs', 6500, 8500, 15], ['TAP-01', 'Water tap brass', 'pcs', 7000, 10000, 5]],
            'Paint' => [['PNT-W4', 'White emulsion 4L', 'tin', 28000, 35000, 6]],
            'Tools' => [['HAM-01', 'Claw hammer', 'pcs', 9000, 13000, 4], ['NAIL-3', 'Wire nails 3 inch 1kg', 'kg', 3800, 5000, 10]],
        ];
        foreach ($catalog as $categoryName => $items) {
            $category = Category::firstOrCreate(['name' => $categoryName]);
            foreach ($items as [$code, $name, $unit, $cost, $price, $reorder]) {
                $product = Product::firstOrCreate(['code' => $code], [
                    'category_id' => $category->id, 'name' => $name, 'unit' => $unit,
                    'cost_price' => $cost, 'selling_price' => $price, 'reorder_level' => $reorder,
                ]);
                foreach ([$main, $branch] as $shop) {
                    StockBalance::firstOrCreate(['shop_id' => $shop->id, 'product_id' => $product->id],
                        ['quantity' => 0, 'avg_cost' => $cost, 'last_cost' => $cost]);
                }
            }
        }
    }
}
