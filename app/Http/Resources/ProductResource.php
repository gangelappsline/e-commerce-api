<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'currency' => $this->whenLoaded('business', fn () => $this->business?->currency),
            'tax_rate' => $this->tax_rate,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'in_stock' => $this->available_stock > 0 || $this->allow_backorder,
            'track_inventory' => $this->track_inventory,
            'requires_shipping' => $this->requires_shipping,
            'is_featured' => $this->is_featured,
            'is_digital' => $this->is_digital,
            'rating' => [
                'average' => $this->rating_average,
                'count' => $this->rating_count,
            ],
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category?->only(['id', 'name', 'slug'])),
            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand?->only(['id', 'name', 'slug', 'logo'])),
            'images' => $this->whenLoaded('images', fn (): array => $this->images->map(fn ($image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'alt_text' => $image->alt_text,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ])->all()),
            'variants' => $this->whenLoaded('variants', fn (): array => $this->variants->where('is_active', true)->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'price' => $variant->price ?? $this->price,
                'compare_at_price' => $variant->compare_at_price ?? $this->compare_at_price,
                'in_stock' => $variant->stock > 0 || $this->allow_backorder,
                'option_values' => $variant->option_values ?? [],
                'image_id' => $variant->image_id,
            ])->values()->all()),
            'tags' => $this->tags ?? [],
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
        ];
    }
}
