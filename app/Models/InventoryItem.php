<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryItem extends TenantModel
{
    protected $fillable = [
        'business_id',
        'product_id',
        'product_variant_id',
        'location_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'available_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
