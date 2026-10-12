<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Coupon extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_order_amount',
        'maximum_discount',
        'usage_limit',
        'usage_limit_per_customer',
        'used_count',
        'applies_to',
        'applies_to_ids',
        'customer_eligibility',
        'eligible_customer_ids',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'applies_to_ids' => 'array',
            'eligible_customer_ids' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
