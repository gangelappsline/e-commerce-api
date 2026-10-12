<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

final class Product extends TenantModel
{
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'category_id', 'brand_id', 'product_type_id', 'name', 'slug', 'sku', 'barcode',
        'short_description', 'description', 'price', 'compare_at_price', 'cost_price', 'tax_rate',
        'tax_included', 'weight', 'dimensions', 'stock', 'low_stock_threshold', 'track_inventory',
        'allow_backorder', 'requires_shipping', 'is_active', 'is_featured', 'is_digital', 'published_at',
        'meta_title', 'meta_description', 'meta_keywords', 'rating_average', 'rating_count', 'view_count',
        'sold_count', 'tags', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_included' => 'boolean',
            'weight' => 'decimal:3',
            'dimensions' => 'array',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'track_inventory' => 'boolean',
            'allow_backorder' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_digital' => 'boolean',
            'published_at' => 'datetime',
            'meta_keywords' => 'array',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
            'view_count' => 'integer',
            'sold_count' => 'integer',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'business_id' => (int) $this->business_id,
            'category_id' => $this->category_id === null ? null : (int) $this->category_id,
            'brand_id' => $this->brand_id === null ? null : (int) $this->brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => strip_tags((string) $this->description),
            'tags' => $this->tags ?? [],
            'price' => (float) $this->price,
            'is_active' => (bool) $this->is_active,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_active && $this->published_at !== null && $this->deleted_at === null;
    }

    public function getAvailableStockAttribute(): int
    {
        if (array_key_exists('inventory_items_sum_available_quantity', $this->attributes)
            && $this->attributes['inventory_items_sum_available_quantity'] !== null) {
            return (int) $this->attributes['inventory_items_sum_available_quantity'];
        }

        if ($this->relationLoaded('inventoryItems')) {
            return (int) $this->inventoryItems->sum('available_quantity');
        }

        return (int) $this->stock;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_product')->withPivot('sort_order')->withTimestamps();
    }

    public function tagsRelation(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_tag')->withTimestamps();
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')
            ->withPivot(['attribute_id']);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
