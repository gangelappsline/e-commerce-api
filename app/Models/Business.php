<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Business extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'slug', 'custom_domain', 'business_type', 'description', 'logo', 'banner',
        'favicon', 'email', 'phone', 'address', 'city', 'state', 'country', 'postal_code', 'currency',
        'language', 'timezone', 'tax_rate', 'tax_included', 'status', 'is_verified', 'verified_at',
        'settings', 'meta_title', 'meta_description', 'meta_keywords',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:4',
            'tax_included' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'settings' => 'array',
            'meta_keywords' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')
            ->using(BusinessUser::class)
            ->withPivot(['role', 'permissions', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_business')
            ->withPivot(['total_orders', 'total_spent', 'loyalty_points', 'first_order_at', 'last_order_at', 'is_blocked', 'notes'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
}
