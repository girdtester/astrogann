<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scale for lakhs of rows (DECISIONS D-75) + idempotent notifications (D-73).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            // Which entry a new_entry notification is about; unique per user so a retried / duplicated
            // fan-out can never notify the same user twice for the same entry.
            $table->foreignId('entry_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->unique(['user_id', 'entry_id']);
            $table->index(['user_id', 'id']); // newest-first lists + "after id" polling
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->index(['entry_date', 'entry_time', 'id']);              // All Entries sorted, no category filter
            $table->index(['admin_id', 'entry_date', 'entry_time']);         // My Entries
            $table->index('created_at');                                     // sort by upload time
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
            $table->index('created_at');
            $table->index(['role_id', 'status']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('end_date');
        });

        Schema::table('user_sessions', function (Blueprint $table) {
            $table->index(['is_active', 'updated_at']); // pruning old ended sessions
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', fn (Blueprint $t) => $t->dropIndex(['user_id', 'created_at']));
        Schema::table('user_sessions', fn (Blueprint $t) => $t->dropIndex(['is_active', 'updated_at']));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropIndex(['end_date']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['name']);
            $t->dropIndex(['created_at']);
            $t->dropIndex(['role_id', 'status']);
        });
        Schema::table('entries', function (Blueprint $t) {
            $t->dropIndex(['entry_date', 'entry_time', 'id']);
            $t->dropIndex(['admin_id', 'entry_date', 'entry_time']);
            $t->dropIndex(['created_at']);
        });
        Schema::table('user_notifications', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'id']);
            $t->dropUnique(['user_id', 'entry_id']);
            $t->dropConstrainedForeignId('entry_id');
        });
    }
};
