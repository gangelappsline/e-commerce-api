<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Attribute extends TenantModel
{
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'type',
        'is_filterable',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
