<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'subtotal',
        'total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
