<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;

final class Banner extends TenantModel
{
    protected $fillable = [
        'business_id',
        'title',
        'image',
        'link',
        'position',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
