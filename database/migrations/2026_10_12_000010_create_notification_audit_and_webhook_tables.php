<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->boolean('email_order_updates')->default(true);
            $table->boolean('email_marketing')->default(false);
            $table->boolean('email_reviews')->default(true);
            $table->boolean('push_order_updates')->default(true);
            $table->boolean('push_marketing')->default(false);
            $table->boolean('sms_order_updates')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'business_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('event', 120);
            $table->string('url', 2048);
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();
            $table->index(['business_id', 'event', 'is_active']);
        });

        Schema::create('webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 120);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
