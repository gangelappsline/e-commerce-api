<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'features',
        'limits',
        'trial_days',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
