<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;

final class ContactMessage extends TenantModel
{
    protected $fillable = [
        'business_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'is_read',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }
}
