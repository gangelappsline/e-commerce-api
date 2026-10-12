<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer?->user?->only(['id', 'name'])),
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'images' => $this->images ?? [],
            'is_verified_purchase' => $this->is_verified_purchase,
            'is_featured' => $this->is_featured,
            'helpful_count' => $this->helpful_count,
            'created_at' => $this->created_at,
        ];
    }
}
