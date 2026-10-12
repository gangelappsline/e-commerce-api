<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'customer_id', 'order_number', 'status', 'payment_status', 'fulfillment_status',
        'currency', 'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total', 'paid_amount',
        'refunded_amount', 'customer_email', 'customer_phone', 'customer_name', 'shipping_address',
        'billing_address', 'shipping_method', 'shipping_carrier', 'tracking_number', 'coupon_id', 'coupon_code',
        'notes', 'internal_notes', 'source', 'ip_address', 'user_agent', 'guest_session_id', 'placed_at',
        'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'paid_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2', 'shipping_address' => 'array', 'billing_address' => 'array',
            'placed_at' => 'datetime', 'confirmed_at' => 'datetime', 'shipped_at' => 'datetime',
            'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(OrderShipment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
