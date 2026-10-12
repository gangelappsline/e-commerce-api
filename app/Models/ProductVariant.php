<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProductVariant extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'product_id',
        'name',
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'cost_price',
        'stock',
        'weight',
        'image_id',
        'option_values',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'option_values' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
