<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Page extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'title',
        'slug',
        'content',
        'type',
        'is_published',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
