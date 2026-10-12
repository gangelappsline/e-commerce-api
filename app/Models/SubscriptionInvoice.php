<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionInvoice extends TenantModel
{
    protected $fillable = [
        'subscription_id',
        'business_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'paid_at',
        'due_at',
        'pdf_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
