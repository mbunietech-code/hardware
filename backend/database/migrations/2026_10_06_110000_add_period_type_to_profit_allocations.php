<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Allocations can be made per day, week or month (owner decision OD-004: all three). */
    public function up(): void
    {
        Schema::table('profit_allocations', function (Blueprint $table) {
            $table->string('period_type', 10)->default('custom')->after('period_end');
        });
    }

    public function down(): void
    {
        Schema::table('profit_allocations', fn (Blueprint $table) => $table->dropColumn('period_type'));
    }
};
