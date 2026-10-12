<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'parent_id', 'sort_order']);
            $table->index(['business_id', 'is_active', 'is_featured']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('product_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->boolean('has_variants')->default(false);
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'slug']);
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->enum('type', ['text', 'select', 'color', 'size', 'number', 'boolean'])->default('text');
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'slug']);
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 150);
            $table->string('slug', 160);
            $table->string('color_code', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->string('slug', 220);
            $table->string('sku', 100);
            $table->string('barcode', 100)->nullable();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->boolean('tax_included')->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->json('dimensions')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_backorder')->default(false);
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_digital')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->json('meta_keywords')->nullable();
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('sold_count')->default(0);
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'slug']);
            $table->unique(['business_id', 'sku']);
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'is_active', 'published_at']);
            $table->index(['business_id', 'category_id']);
            $table->index(['business_id', 'brand_id']);
            $table->index(['business_id', 'price']);
            $table->index(['business_id', 'stock']);
            $table->fullText(['name', 'short_description', 'description']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->index();
            $table->string('url');
            $table->string('alt_text', 250)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('sku', 100);
            $table->string('barcode', 100)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('weight', 10, 3)->nullable();
            $table->foreignId('image_id')->nullable()->constrained('product_images')->nullOnDelete();
            $table->json('option_values')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'sku']);
            $table->index(['product_id', 'is_active', 'sort_order']);
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'attribute_id', 'attribute_value_id'], 'product_attribute_value_unique');
            $table->index(['attribute_id', 'attribute_value_id']);
        });

        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->timestamps();
            $table->unique(['business_id', 'slug']);
        });

        Schema::create('product_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained('product_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'product_tag_id']);
        });

        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('type', ['manual', 'automatic'])->default('manual');
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'is_active', 'sort_order']);
        });

        Schema::create('collection_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['collection_id', 'product_id']);
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->index();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 180)->nullable();
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'product_id', 'is_approved']);
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('review_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_helpful');
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropForeign(['product_variant_id']);
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropForeign(['image_id']);
        });
        Schema::dropIfExists('review_votes');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
