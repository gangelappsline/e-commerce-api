<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryLocation extends TenantModel
{
    protected $fillable = [
        'business_id',
        'name',
        'address',
        'phone',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
