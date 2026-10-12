<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;

final class Discount extends TenantModel
{
    protected $fillable = [
        'business_id',
        'name',
        'type',
        'value',
        'conditions',
        'priority',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'conditions' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
