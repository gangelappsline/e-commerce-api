<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('payment_method', ['credit_card', 'debit_card', 'paypal', 'cash_on_delivery', 'bank_transfer', 'wallet', 'other'])->default('other');
            $table->string('payment_gateway', 80)->nullable();
            $table->string('transaction_id', 180)->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed', 'refunded', 'partially_refunded'])->default('pending')->index();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->index();
            $table->string('gateway_refund_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('provider', 80)->nullable();
            $table->string('last_four', 4)->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->text('token');
            $table->timestamps();
            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
