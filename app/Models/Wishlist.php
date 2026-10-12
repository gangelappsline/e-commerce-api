<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Wishlist extends TenantModel
{
    protected $fillable = [
        'customer_id',
        'business_id',
        'name',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }
}
