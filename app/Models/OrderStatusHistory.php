<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'user_id',
        'from_status',
        'to_status',
        'notes',
        'is_customer_notified',
    ];

    protected function casts(): array
    {
        return [
            'is_customer_notified' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
