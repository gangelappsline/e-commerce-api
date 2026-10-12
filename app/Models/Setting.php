<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;

final class Setting extends TenantModel
{
    protected $fillable = [
        'business_id',
        'key',
        'value',
        'group',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
