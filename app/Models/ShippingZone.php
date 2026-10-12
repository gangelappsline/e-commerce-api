<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShippingZone extends TenantModel
{
    protected $fillable = [
        'business_id',
        'name',
        'countries',
        'states',
        'postal_codes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'states' => 'array',
            'postal_codes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }
}
