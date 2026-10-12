<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderReturn extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'reason',
        'status',
        'refund_amount',
        'refund_method',
        'items',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'items' => 'array',
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
}
