<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Customer extends Model
{
    protected $fillable = [
        'user_id', 'default_business_id', 'total_orders', 'total_spent', 'loyalty_points',
        'accepts_marketing', 'tags', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_spent' => 'decimal:2',
            'loyalty_points' => 'integer',
            'accepts_marketing' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'customer_business')
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

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
