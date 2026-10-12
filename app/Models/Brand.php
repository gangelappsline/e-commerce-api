<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Brand extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'description',
        'logo',
        'website',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
