<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping', 'buy_x_get_y']);
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('minimum_order_amount', 12, 2)->nullable();
            $table->decimal('maximum_discount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->enum('applies_to', ['all', 'specific_products', 'specific_categories', 'specific_collections'])->default('all');
            $table->json('applies_to_ids')->nullable();
            $table->enum('customer_eligibility', ['all', 'new_customers', 'specific_customers'])->default('all');
            $table->json('eligible_customer_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'is_active', 'starts_at', 'expires_at']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });

        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('used_at')->useCurrent();
            $table->timestamps();
            $table->index(['coupon_id', 'customer_id']);
        });

        Schema::create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 40);
            $table->decimal('value', 12, 2);
            $table->json('conditions')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'is_active', 'priority']);
        });

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('countries')->nullable();
            $table->json('states')->nullable();
            $table->json('postal_codes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete();
            $table->string('name', 150);
            $table->enum('type', ['flat_rate', 'free', 'weight_based', 'price_based', 'carrier'])->default('flat_rate');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->decimal('max_order_amount', 12, 2)->nullable();
            $table->decimal('weight_from', 10, 3)->nullable();
            $table->decimal('weight_to', 10, 3)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['business_id', 'zone_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('coupon_usages');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['coupon_id']);
        });
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropForeign(['coupon_id']);
        });
        Schema::dropIfExists('coupons');
    }
};
