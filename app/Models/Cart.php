<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Cart extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'customer_id', 'session_id', 'currency', 'subtotal', 'discount_total',
        'tax_total', 'shipping_total', 'grand_total', 'coupon_id', 'notes', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
