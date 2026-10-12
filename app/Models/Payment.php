<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Payment extends TenantModel
{
    protected $fillable = [
        'business_id',
        'order_id',
        'customer_id',
        'payment_method',
        'payment_gateway',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'gateway_response',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
