<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerBusiness extends Model
{
    protected $table = 'customer_business';

    protected $fillable = [
        'customer_id',
        'business_id',
        'total_orders',
        'total_spent',
        'loyalty_points',
        'first_order_at',
        'last_order_at',
        'is_blocked',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_spent' => 'decimal:2',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'is_blocked' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
