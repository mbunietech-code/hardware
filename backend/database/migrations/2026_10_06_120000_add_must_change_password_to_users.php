<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Users with a temporary or default password must choose their own on next sign-in. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
        // Existing demo accounts still use the default password.
        DB::table('users')->whereIn('email', ['admin@hardware.test', 'shop@hardware.test', 'branch@hardware.test'])
            ->update(['must_change_password' => true]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_change_password'));
    }
};
