<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AbandonedCart extends TenantModel
{
    protected $fillable = [
        'business_id',
        'customer_id',
        'cart_snapshot',
        'email',
        'reminder_sent_at',
        'recovered_at',
    ];

    protected function casts(): array
    {
        return [
            'cart_snapshot' => 'array',
            'reminder_sent_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
