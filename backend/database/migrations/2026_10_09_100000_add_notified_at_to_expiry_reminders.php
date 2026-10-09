<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the in-app "expires in N days" notice was shown (D-95) — independent of e-mail retries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expiry_reminders', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('expiry_reminders', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
