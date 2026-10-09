<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements written by the Super Admin and sent to users as in-app notifications (+ push) (D-100).
 * Each user's copy is a normal user_notifications row (type "announcement") pointing back here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('message', 500);
            $table->string('audience', 20)->default('all'); // all | active | inactive
            $table->unsignedInteger('recipients')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            // Deleting an announcement removes it from every user's Notifications.
            $table->foreignId('announcement_id')->nullable()->after('entry_id')->constrained()->cascadeOnDelete();
            // Retried fan-out can never notify a user twice for the same announcement.
            $table->unique(['user_id', 'announcement_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'announcement_id']);
            $table->dropConstrainedForeignId('announcement_id');
        });
        Schema::dropIfExists('announcements');
    }
};
