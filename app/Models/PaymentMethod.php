<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentMethod extends Model
{
    protected $fillable = ['customer_id', 'type', 'provider', 'last_four', 'expiry_month', 'expiry_year', 'is_default', 'token'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'token' => 'encrypted'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
