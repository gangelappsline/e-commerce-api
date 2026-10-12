<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'variant_name',
        'unit_price',
        'quantity',
        'discount',
        'tax',
        'subtotal',
        'total',
        'fulfilled_quantity',
        'refunded_quantity',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
