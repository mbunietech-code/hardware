<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offline-capable records carry: local_uuid (idempotency key from the device),
     * device_id, client_created_at (device clock) and synced_at (server receive time).
     */
    private function syncColumns(Blueprint $table): void
    {
        $table->uuid('local_uuid')->nullable()->unique();
        $table->string('device_id', 100)->nullable();
        $table->string('source', 20)->default('web');
        $table->timestamp('client_created_at')->nullable();
        $table->timestamp('synced_at')->nullable();
    }

    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('device_id', 100);
            $table->string('name')->nullable();
            $table->string('platform', 30)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('unit', 30)->default('pcs');
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->uuid('local_uuid')->nullable()->unique();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('local_uuid')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('local_uuid')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('avg_cost', 15, 2)->default(0);
            $table->decimal('last_cost', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['shop_id', 'product_id']);
        });

        Schema::create('daily_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->date('business_date');
            $table->string('status', 20)->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users');
            $table->timestamp('opened_at')->nullable();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->json('totals')->nullable();
            $table->json('client_totals')->nullable();
            $table->text('closing_notes')->nullable();
            $table->text('exceptions')->nullable();
            $table->unsignedInteger('reopen_count')->default(0);
            $this->syncColumns($table);
            $table->timestamps();
            $table->unique(['shop_id', 'business_date']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('daily_session_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('sale_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('payment_method', 30)->default('cash');
            $table->string('payment_status', 20)->default('paid');
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $this->syncColumns($table);
            $table->timestamps();
            $table->index(['shop_id', 'sale_date']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('daily_session_id')->nullable()->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('purchase_date');
            $table->string('invoice_number', 60)->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('payment_method', 30)->default('cash');
            $table->string('payment_status', 20)->default('paid');
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $this->syncColumns($table);
            $table->timestamps();
            $table->index(['shop_id', 'purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('direction', 10); // in | out
            $table->decimal('quantity', 14, 3);
            $table->decimal('before_qty', 14, 3);
            $table->decimal('after_qty', 14, 3);
            $table->string('reason');
            $table->text('notes')->nullable();
            $this->syncColumns($table);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('type', 30)->index();
            $table->decimal('quantity', 14, 3); // signed: + increases, - decreases
            $table->decimal('before_qty', 14, 3);
            $table->decimal('after_qty', 14, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->nullableMorphs('source');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shop_id', 'product_id', 'created_at']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('reduces_profit')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('daily_session_id')->nullable()->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30)->default('cash');
            $table->string('reason');
            $table->string('status', 20)->default('active');
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $this->syncColumns($table);
            $table->timestamps();
            $table->index(['shop_id', 'expense_date']);
        });

        Schema::create('capital_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained();
            $table->foreignId('daily_session_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 20); // injection | withdrawal
            $table->decimal('amount', 15, 2);
            $table->date('entry_date');
            $table->string('payment_method', 30)->default('cash');
            $table->string('reason');
            $table->string('status', 20)->default('active');
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $this->syncColumns($table);
            $table->timestamps();
        });

        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('daily_session_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 20); // receivable (customer owes shop) | payable (shop owes supplier)
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->string('party_name');
            $table->string('party_phone', 30)->nullable();
            $table->decimal('original_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2);
            $table->date('debt_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open'); // open | partial | paid | cancelled
            $table->nullableMorphs('source');
            $table->text('notes')->nullable();
            $this->syncColumns($table);
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('daily_session_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method', 30)->default('cash');
            $table->string('notes')->nullable();
            $this->syncColumns($table);
            $table->timestamps();
        });

        Schema::create('profit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('cost_of_goods', 15, 2)->default(0);
            $table->decimal('expenses', 15, 2)->default(0);
            $table->decimal('profit_amount', 15, 2);
            $table->decimal('primary_percent', 5, 2);
            $table->decimal('secondary_percent', 5, 2);
            $table->decimal('primary_amount', 15, 2);
            $table->decimal('secondary_amount', 15, 2);
            $table->string('primary_label');
            $table->string('secondary_label');
            $table->json('formula_config');
            $table->string('status', 20)->default('draft'); // draft | approved | cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('shop_id')->nullable()->constrained();
            $table->string('audience', 20)->default('admins'); // admins | shop | user
            $table->string('type', 40)->index();
            $table->string('title');
            $table->text('message');
            $table->nullableMorphs('related');
            $table->string('dedupe_key')->nullable()->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('shop_id')->nullable()->constrained();
            $table->string('action', 60)->index();
            $table->string('entity_type', 60)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->string('source', 20)->default('web');
            $table->string('device_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('sync_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('local_uuid');
            $table->string('entity', 40);
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('shop_id')->nullable()->constrained();
            $table->string('device_id', 100)->nullable();
            $table->string('status', 20)->index(); // accepted | rejected | conflict | resolved
            $table->unsignedBigInteger('server_id')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 30)->nullable();
            $table->timestamps();
            $table->unique(['local_uuid', 'entity']);
        });
    }

    public function down(): void
    {
        foreach ([
            'sync_receipts', 'audit_logs', 'system_notifications', 'profit_allocations', 'debt_payments', 'debts',
            'capital_entries', 'expenses', 'expense_categories', 'stock_movements', 'stock_adjustments',
            'purchase_items', 'purchases', 'sale_items', 'sales', 'daily_sessions', 'stock_balances',
            'customers', 'suppliers', 'products', 'categories', 'devices', 'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
