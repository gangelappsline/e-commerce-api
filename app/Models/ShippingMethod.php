<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShippingMethod extends TenantModel
{
    protected $fillable = [
        'business_id',
        'zone_id',
        'name',
        'type',
        'price',
        'min_order_amount',
        'max_order_amount',
        'weight_from',
        'weight_to',
        'carrier',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_order_amount' => 'decimal:2',
            'weight_from' => 'decimal:3',
            'weight_to' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
