<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('daily_session_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('return_date');
            $table->decimal('return_value', 15, 2);   // value of the goods returned (reduces revenue)
            $table->decimal('debt_reduction', 15, 2)->default(0); // part taken off the customer's credit balance
            $table->decimal('refund_amount', 15, 2)->default(0);  // money handed back to the customer
            $table->string('refund_method', 30)->default('cash');
            $table->decimal('cost_restocked', 15, 2)->default(0);  // cost of goods back on the shelf (reduces COGS)
            $table->string('reason');
            $table->uuid('local_uuid')->nullable()->unique();
            $table->string('device_id', 100)->nullable();
            $table->string('source', 20)->default('web');
            $table->timestamps();
            $table->index(['shop_id', 'return_date']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_value', 15, 2);
            $table->decimal('line_value', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->boolean('restocked')->default(true);
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('returned_total', 15, 2)->default(0)->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('returned_total'));
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
