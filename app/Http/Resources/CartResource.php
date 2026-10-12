<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'coupon' => $this->whenLoaded('coupon', fn (): ?array => $this->coupon?->only(['code', 'name', 'type', 'value'])),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'subtotal' => $item->subtotal,
                'total' => $item->total,
                'product' => $item->relationLoaded('product') && $item->product !== null
                    ? (new ProductResource($item->product))->resolve($request)
                    : null,
            ])->all()),
            'updated_at' => $this->updated_at,
        ];
    }
}
