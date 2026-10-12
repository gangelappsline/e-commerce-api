<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductType extends TenantModel
{
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'has_variants',
        'attributes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_variants' => 'boolean',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
