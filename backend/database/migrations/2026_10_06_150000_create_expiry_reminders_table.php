<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The saved list of "expires in N days" reminders (DECISIONS D-88): collected once a day (18:00), then sent one by
 * one by the every-minute cron. One row per subscription period (subscription_id + end_date) — never twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiry_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('end_date');
            $table->unsignedSmallInteger('days_left');
            $table->string('status', 10)->default('pending'); // pending | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps(); // created_at = collected at

            $table->unique(['subscription_id', 'end_date']);
            $table->index(['status', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_reminders');
    }
};
