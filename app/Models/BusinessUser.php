<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class BusinessUser extends Pivot
{
    protected $table = 'business_user';
    public $incrementing = true;

    protected $fillable = ['business_id', 'user_id', 'role', 'permissions', 'joined_at', 'is_active'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'joined_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
