<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class Webhook extends TenantModel
{
    protected $fillable = ['business_id', 'event', 'url', 'secret', 'is_active', 'last_triggered_at', 'failure_count'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'is_active' => 'boolean', 'last_triggered_at' => 'datetime'];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }
}
