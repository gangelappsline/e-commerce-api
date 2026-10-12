<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AttributeValue extends Model
{
    protected $fillable = [
        'attribute_id',
        'value',
        'slug',
        'color_code',
        'sort_order',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
