<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderShipment extends Model
{
    protected $fillable = [
        'order_id',
        'carrier',
        'tracking_number',
        'tracking_url',
        'shipped_at',
        'delivered_at',
        'items',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'items' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
