<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BlogPost extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'category',
        'tags',
        'status',
        'published_at',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
