<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Subscription extends TenantModel
{
    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'cancellation_reason',
        'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }
}
