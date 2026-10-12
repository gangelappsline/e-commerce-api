<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'fulfillment_status' => $this->fulfillment_status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'shipping_total' => $this->shipping_total,
            'grand_total' => $this->grand_total,
            'paid_amount' => $this->paid_amount,
            'refunded_amount' => $this->refunded_amount,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'customer_name' => $this->customer_name,
            'shipping_address' => $this->shipping_address,
            'billing_address' => $this->billing_address,
            'shipping_method' => $this->shipping_method,
            'shipping_carrier' => $this->shipping_carrier,
            'tracking_number' => $this->tracking_number,
            'coupon_code' => $this->coupon_code,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'variant_name' => $item->variant_name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'subtotal' => $item->subtotal,
                'total' => $item->total,
                'fulfilled_quantity' => $item->fulfilled_quantity,
                'refunded_quantity' => $item->refunded_quantity,
            ])->all()),
            'tracking' => $this->whenLoaded('shipments', fn (): array => $this->shipments->map(fn ($shipment): array => [
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'status' => $shipment->status,
                'shipped_at' => $shipment->shipped_at,
                'delivered_at' => $shipment->delivered_at,
            ])->all()),
            'placed_at' => $this->placed_at,
            'confirmed_at' => $this->confirmed_at,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
        ];
    }
}
