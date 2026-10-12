<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Collection extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'description',
        'image',
        'type',
        'rules',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product');
    }
}
