<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;

final class Newsletter extends TenantModel
{
    protected $fillable = [
        'business_id',
        'email',
        'name',
        'is_subscribed',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_subscribed' => 'boolean',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }
}
