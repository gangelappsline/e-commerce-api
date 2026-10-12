<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ProductTag extends TenantModel
{
    protected $fillable = [
        'business_id',
        'name',
        'slug',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag');
    }
}
