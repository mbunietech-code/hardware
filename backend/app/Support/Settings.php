<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Business settings. Every "Pending Decision" from the documentation is a setting
 * here so the owner can decide without code changes.
 */
class Settings
{
    public const DEFINITIONS = [
        'business_name' => ['default' => 'Hardware Business', 'type' => 'string', 'label' => 'Business name', 'group' => 'General'],
        'currency' => ['default' => 'TZS', 'type' => 'string', 'label' => 'Currency code', 'group' => 'General'],
        'receipt_footer' => ['default' => 'Thank you for your business!', 'type' => 'string', 'label' => 'Receipt footer text', 'group' => 'General'],

        'require_open_day' => ['default' => true, 'type' => 'bool', 'label' => 'Require an open business day before recording transactions', 'group' => 'Operations'],
        'negative_stock_policy' => ['default' => 'block', 'type' => 'select', 'options' => ['block' => 'Block sale', 'allow' => 'Allow (stock goes negative)'], 'label' => 'Negative stock policy (OD-008)', 'group' => 'Operations'],
        'sell_below_cost_policy' => ['default' => 'warn', 'type' => 'select', 'options' => ['allow' => 'Allow silently', 'warn' => 'Allow with warning', 'block' => 'Block'], 'label' => 'Selling below cost', 'group' => 'Operations'],
        'discounts_enabled' => ['default' => true, 'type' => 'bool', 'label' => 'Allow discounts on sales', 'group' => 'Operations'],
        'auto_debt_from_credit' => ['default' => true, 'type' => 'bool', 'label' => 'Unpaid sale/purchase balances automatically create debts', 'group' => 'Operations'],
        'update_product_cost_on_purchase' => ['default' => true, 'type' => 'bool', 'label' => 'Update product cost price from latest purchase', 'group' => 'Operations'],
        'closing_reminder_hour' => ['default' => 18, 'type' => 'int', 'label' => 'Daily closing reminder hour (0-23)', 'group' => 'Notifications'],
        'debt_reminder_days' => ['default' => 2, 'type' => 'int', 'label' => 'Remind about debts due within N days', 'group' => 'Notifications'],

        'profit_rules_approved' => ['default' => false, 'type' => 'bool', 'label' => 'Owner has approved the profit rules below (removes "provisional" labels)', 'group' => 'Finance'],
        'costing_method' => ['default' => 'weighted_average', 'type' => 'select', 'options' => ['weighted_average' => 'Weighted average cost', 'latest_cost' => 'Latest purchase cost', 'product_cost' => 'Product cost price (fixed)'], 'label' => 'Stock costing method (OD-002)', 'group' => 'Finance'],
        'profit_formula' => ['default' => 'net', 'type' => 'select', 'options' => ['gross' => 'Gross: Sales − Cost of goods sold', 'net' => 'Net: Gross − profit-reducing expenses'], 'label' => 'Profit formula (OD-001)', 'group' => 'Finance'],
        'allocation_primary_percent' => ['default' => 60, 'type' => 'int', 'label' => 'Allocation primary percent', 'group' => 'Finance'],
        'allocation_primary_label' => ['default' => '60% Allocation', 'type' => 'string', 'label' => 'Primary allocation label (OD-003)', 'group' => 'Finance'],
        'allocation_secondary_label' => ['default' => '40% Allocation', 'type' => 'string', 'label' => 'Secondary allocation label (OD-003)', 'group' => 'Finance'],
        'allocation_timing' => ['default' => 'monthly', 'type' => 'select', 'options' => ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'per_closing' => 'Per closing', 'on_approval' => 'When owner approves'], 'label' => 'Allocation timing (OD-004)', 'group' => 'Finance'],
        'tax_enabled' => ['default' => false, 'type' => 'bool', 'label' => 'Tax / VAT (OD-010 – not applied in calculations yet)', 'group' => 'Finance'],

        'sync_conflict_policy' => ['default' => 'manual', 'type' => 'select', 'options' => ['manual' => 'Hold conflicts for Super Admin review'], 'label' => 'Sync conflict policy (OD-009)', 'group' => 'Sync'],
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $stored = Schema::hasTable('settings') ? Setting::pluck('value', 'key')->all() : [];
            self::$cache = [];
            foreach (self::DEFINITIONS as $key => $def) {
                self::$cache[$key] = array_key_exists($key, $stored)
                    ? self::cast($stored[$key], $def['type'])
                    : $def['default'];
            }
        }

        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (! isset(self::DEFINITIONS[$key])) {
            return;
        }
        $value = self::cast($value, self::DEFINITIONS[$key]['type']);
        Setting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            default => (string) $value,
        };
    }

    public static function allocationPercents(): array
    {
        $primary = max(0, min(100, (int) self::get('allocation_primary_percent')));

        return [$primary, 100 - $primary];
    }
}
