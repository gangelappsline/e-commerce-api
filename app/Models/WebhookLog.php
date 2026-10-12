<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WebhookLog extends Model
{
    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response_status',
        'response_body',
        'attempts',
        'succeeded_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'succeeded_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
