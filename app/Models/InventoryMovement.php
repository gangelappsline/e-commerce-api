<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryMovement extends TenantModel
{
    protected $fillable = [
        'business_id',
        'inventory_item_id',
        'user_id',
        'type',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'reference_type',
        'reference_id',
        'notes',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
