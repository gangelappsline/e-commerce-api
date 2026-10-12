<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CollectionProduct extends Model
{
    protected $table = 'collection_product';

    protected $fillable = [
        'collection_id',
        'product_id',
        'sort_order',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
