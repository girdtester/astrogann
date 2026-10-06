<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a session ended (logout / replaced / deactivated) so the API can answer a revoked token with the
 * right error code, e.g. "session_replaced" (DECISIONS D-52).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('ended_reason', 20)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn('ended_reason');
        });
    }
};
