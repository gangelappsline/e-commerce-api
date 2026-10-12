<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class InventoryService
{
    public function available(Product $product, ?ProductVariant $variant = null): int
    {
        $query = InventoryItem::query()
            ->where('business_id', $product->business_id)
            ->where('product_id', $product->id)
            ->when($variant !== null, fn ($builder) => $builder->where('product_variant_id', $variant->id),
                fn ($builder) => $builder->whereNull('product_variant_id'));

        if ($query->exists()) {
            return max(0, (int) $query->sum('available_quantity'));
        }

        return max(0, (int) ($variant?->stock ?? $product->stock));
    }

    public function decreaseForOrderItem(Product $product, ?ProductVariant $variant, int $quantity, OrderItem $orderItem, ?User $actor): void
    {
        if (! $product->track_inventory || $quantity < 1) {
            return;
        }

        $stockOwner = $variant ?? $product;
        $variantId = $variant?->id;
        $baseQuery = InventoryItem::query()
            ->where('business_id', $product->business_id)
            ->where('product_id', $product->id)
            ->when($variantId !== null, fn ($query) => $query->where('product_variant_id', $variantId),
                fn ($query) => $query->whereNull('product_variant_id'));
        $hasLocationStock = (clone $baseQuery)->exists();

        if ($hasLocationStock) {
            $items = (clone $baseQuery)->whereRaw('quantity > reserved_quantity')
                ->orderByDesc('location_id')
                ->lockForUpdate()
                ->get();
            $available = (int) $items->sum(fn (InventoryItem $item): int => (int) $item->available_quantity);
            if ($available < $quantity && ! $product->allow_backorder) {
                throw new ApiException('OUT_OF_STOCK', 'One or more products are no longer available in the requested quantity.', 409, ['product_id' => $product->id]);
            }

            $remaining = min($quantity, $available);
            foreach ($items as $item) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min($remaining, (int) $item->available_quantity);
                if ($take < 1) {
                    continue;
                }
                $previous = (int) $item->quantity;
                $item->decrement('quantity', $take);
                $item->refresh();
                $this->recordMovement($product, $item, -$take, $previous, (int) $item->quantity, $orderItem, $actor);
                $remaining -= $take;
            }

            return;
        }

        $stockOwner = $stockOwner->newQuery()->lockForUpdate()->findOrFail($stockOwner->getKey());
        $previous = (int) $stockOwner->stock;
        if ($previous < $quantity && ! $product->allow_backorder) {
            throw new ApiException('OUT_OF_STOCK', 'One or more products are no longer available in the requested quantity.', 409, ['product_id' => $product->id]);
        }

        $take = min($quantity, $previous);
        if ($take > 0) {
            $stockOwner->decrement('stock', $take);
            $stockOwner->refresh();
            $this->recordMovement($product, null, -$take, $previous, (int) $stockOwner->stock, $orderItem, $actor);
        }
    }

    public function restoreForOrderItem(OrderItem $orderItem, ?User $actor, string $type = 'return'): void
    {
        $product = $orderItem->product;
        if ($product === null || ! $product->track_inventory) {
            return;
        }

        $alreadyRestored = InventoryMovement::query()
            ->where('reference_type', OrderItem::class)
            ->where('reference_id', $orderItem->id)
            ->where('type', 'return')
            ->exists();
        if ($alreadyRestored) {
            return;
        }

        $movements = InventoryMovement::query()
            ->where('reference_type', OrderItem::class)
            ->where('reference_id', $orderItem->id)
            ->where('type', 'sale')
            ->whereNotNull('inventory_item_id')
            ->get();

        if ($movements->isNotEmpty()) {
            foreach ($movements as $movement) {
                $item = InventoryItem::query()->lockForUpdate()->find($movement->inventory_item_id);
                if ($item === null) {
                    continue;
                }
                $previous = (int) $item->quantity;
                $qty = abs((int) $movement->quantity);
                $item->increment('quantity', $qty);
                $item->refresh();
                InventoryMovement::create([
                    'business_id' => $product->business_id,
                    'inventory_item_id' => $item->id,
                    'user_id' => $actor?->id,
                    'type' => $type,
                    'quantity' => $qty,
                    'previous_quantity' => $previous,
                    'new_quantity' => (int) $item->quantity,
                    'reference_type' => OrderItem::class,
                    'reference_id' => $orderItem->id,
                    'notes' => 'Inventory returned from order lifecycle.',
                ]);
            }

            return;
        }

        $stockOwner = $orderItem->product_variant_id !== null
            ? ProductVariant::query()->lockForUpdate()->find($orderItem->product_variant_id)
            : Product::query()->lockForUpdate()->find($product->id);
        if ($stockOwner === null) {
            return;
        }

        $soldQuantity = abs((int) InventoryMovement::query()
            ->where('reference_type', OrderItem::class)
            ->where('reference_id', $orderItem->id)
            ->where('type', 'sale')
            ->whereNull('inventory_item_id')
            ->sum('quantity'));
        if ($soldQuantity < 1) {
            return;
        }

        $previous = (int) $stockOwner->stock;
        $stockOwner->increment('stock', $soldQuantity);
        $stockOwner->refresh();
        $this->recordMovement($product, null, $soldQuantity, $previous, (int) $stockOwner->stock, $orderItem, $actor, $type);
    }

    public function adjust(InventoryItem $item, int $change, ?User $actor, string $notes): InventoryMovement
    {
        return DB::transaction(function () use ($item, $change, $actor, $notes): InventoryMovement {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $previous = (int) $locked->quantity;
            $new = $previous + $change;
            if ($new < (int) $locked->reserved_quantity) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'Inventory cannot be reduced below reserved stock.', 422);
            }

            $locked->update(['quantity' => $new]);

            return InventoryMovement::create([
                'business_id' => $locked->business_id,
                'inventory_item_id' => $locked->id,
                'user_id' => $actor?->id,
                'type' => 'adjustment',
                'quantity' => $change,
                'previous_quantity' => $previous,
                'new_quantity' => $new,
                'notes' => $notes,
            ]);
        });
    }

    private function recordMovement(
        Product $product,
        ?InventoryItem $item,
        int $change,
        int $previous,
        int $new,
        OrderItem $orderItem,
        ?User $actor,
        string $type = 'sale',
    ): void {
        InventoryMovement::create([
            'business_id' => $product->business_id,
            'inventory_item_id' => $item?->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'quantity' => $change,
            'previous_quantity' => $previous,
            'new_quantity' => $new,
            'reference_type' => OrderItem::class,
            'reference_id' => $orderItem->id,
            'notes' => 'Inventory movement created from order lifecycle.',
        ]);
    }
}
