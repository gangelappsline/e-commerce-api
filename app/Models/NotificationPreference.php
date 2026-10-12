<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'business_id',
        'email_order_updates',
        'email_marketing',
        'email_reviews',
        'push_order_updates',
        'push_marketing',
        'sms_order_updates',
    ];

    protected function casts(): array
    {
        return [
            'email_order_updates' => 'boolean',
            'email_marketing' => 'boolean',
            'email_reviews' => 'boolean',
            'push_order_updates' => 'boolean',
            'push_marketing' => 'boolean',
            'sms_order_updates' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
