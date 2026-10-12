<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->enum('business_type', ['fashion', 'electronics', 'grocery', 'pharmacy', 'restaurant', 'jewelry', 'books', 'beauty', 'home', 'sports', 'toys', 'other'])->default('other')->index();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->string('favicon')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('language', 10)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->boolean('tax_included')->default(false);
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('pending')->index();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('settings')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->json('meta_keywords')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('business_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'seller', 'staff'])->default('staff');
            $table->json('permissions')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->default('Home');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 40)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('country', 100);
            $table->string('postal_code', 30)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_default_shipping']);
            $table->index(['user_id', 'is_default_billing']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('default_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->boolean('accepts_marketing')->default(false);
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['default_business_id', 'total_orders']);
        });

        Schema::create('customer_business', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'business_id']);
            $table->index(['business_id', 'is_blocked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_business');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('user_addresses');
        Schema::dropIfExists('business_user');
        Schema::dropIfExists('businesses');
    }
};
