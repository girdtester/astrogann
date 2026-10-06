<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * All AstroGann domain tables. See docs/DATABASE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('image_path');
            $table->date('entry_date');
            $table->time('entry_time');
            $table->timestamp('created_at')->nullable();

            $table->index(['category_id', 'entry_date', 'entry_time']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'end_date']);
            $table->index(['status', 'end_date']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('transaction_ref', 100)->nullable();
            $table->string('status', 20)->default('paid');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_type', 10);
            $table->string('fcm_token', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('message', 500);
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'is_read']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('categories');
    }
};
