<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to', 20);
            $table->text('message');
            $table->string('purpose', 30)->index(); // debt_reminder | daily_summary | test | manual
            $table->string('status', 20)->index(); // sent | failed | skipped
            $table->string('provider_request_id')->nullable();
            $table->text('error')->nullable();
            $table->nullableMorphs('related');
            $table->foreignId('shop_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
