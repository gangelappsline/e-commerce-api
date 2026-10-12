<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 40);
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'returned'])->default('pending')->index();
            $table->enum('payment_status', ['pending', 'authorized', 'paid', 'partially_paid', 'refunded', 'failed'])->default('pending')->index();
            $table->enum('fulfillment_status', ['unfulfilled', 'partial', 'fulfilled'])->default('unfulfilled')->index();
            $table->char('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('customer_email');
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_name', 200);
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable()->index();
            $table->string('coupon_code')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->enum('source', ['web', 'mobile', 'pos', 'api'])->default('web');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('guest_session_id')->nullable()->index();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'order_number']);
            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'customer_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name', 200);
            $table->string('product_sku', 100)->nullable();
            $table->string('variant_name', 180)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('fulfilled_quantity')->default(0);
            $table->unsignedInteger('refunded_quantity')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'product_id']);
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->boolean('is_customer_notified')->default(false);
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('items')->nullable();
            $table->string('status', 40)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->enum('status', ['requested', 'approved', 'rejected', 'received', 'refunded'])->default('requested')->index();
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('refund_method', 40)->nullable();
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('abandoned_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->json('cart_snapshot');
            $table->string('email')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'recovered_at', 'created_at']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
        });
        Schema::dropIfExists('abandoned_carts');
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('order_shipments');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
