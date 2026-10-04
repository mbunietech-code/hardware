<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Title/message are stored as English templates; params fill them in the reader's language. */
    public function up(): void
    {
        Schema::table('system_notifications', function (Blueprint $table) {
            $table->json('params')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('system_notifications', function (Blueprint $table) {
            $table->dropColumn('params');
        });
    }
};
