<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    public function scopeForBusiness(Builder $query, int|Business $business): Builder
    {
        $businessId = $business instanceof Business ? (int) $business->getKey() : $business;

        return $query->where($this->qualifyColumn('business_id'), $businessId);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
