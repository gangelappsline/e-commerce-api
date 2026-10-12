<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Review extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'product_id',
        'customer_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'images',
        'is_verified_purchase',
        'is_approved',
        'is_featured',
        'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_verified_purchase' => 'boolean',
            'is_approved' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }
}
