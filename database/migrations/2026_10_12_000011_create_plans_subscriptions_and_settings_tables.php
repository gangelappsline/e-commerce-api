<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['trial', 'active', 'past_due', 'cancelled', 'expired'])->default('trial')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('payment_method', 60)->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'ends_at']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 80);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('pdf_url')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'invoice_number']);
            $table->index(['subscription_id', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 180);
            $table->json('value')->nullable();
            $table->string('group', 100)->default('general');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['business_id', 'key']);
            $table->index(['business_id', 'group', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
