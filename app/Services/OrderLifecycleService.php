<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Jobs\DispatchWebhookJob;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use App\Mail\OrderStatusChangedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderShipment;
use App\Models\OrderStatusHistory;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class OrderLifecycleService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function changeStatus(Order $order, string $status, ?User $actor, ?string $notes = null): Order
    {
        return DB::transaction(function () use ($order, $status, $actor, $notes): Order {
            $locked = Order::query()->where('business_id', $order->business_id)->lockForUpdate()->findOrFail($order->id);
            $transitions = [
                'pending' => ['confirmed', 'processing', 'cancelled'],
                'confirmed' => ['processing', 'cancelled'],
                'processing' => ['shipped', 'cancelled'],
                'shipped' => ['delivered', 'returned'],
                'delivered' => ['returned', 'refunded'],
                'cancelled' => [],
                'refunded' => [],
                'returned' => ['refunded'],
            ];
            if (! in_array($status, $transitions[$locked->status] ?? [], true)) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'The requested order status transition is not allowed.', 422, [
                    'from_status' => $locked->status,
                    'to_status' => $status,
                ]);
            }
            if ($status === 'cancelled' && (float) $locked->paid_amount > (float) $locked->refunded_amount) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'A paid order must be refunded before it can be cancelled.', 422);
            }
            $from = $locked->status;
            $updates = ['status' => $status];
            if ($status === 'confirmed') {
                $updates['confirmed_at'] = now();
            } elseif ($status === 'shipped') {
                $updates['shipped_at'] = now();
            } elseif ($status === 'delivered') {
                $updates['delivered_at'] = now();
            } elseif ($status === 'cancelled') {
                $updates['cancelled_at'] = now();
                $updates['cancellation_reason'] = $notes;
                foreach ($locked->items as $item) {
                    $item->loadMissing('product');
                    $this->inventory->restoreForOrderItem($item, $actor);
                }
            }
            $locked->forceFill($updates)->save();
            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'user_id' => $actor?->id,
                'from_status' => $from,
                'to_status' => $status,
                'notes' => $notes,
                'is_customer_notified' => false,
            ]);

            $fresh = $locked->fresh(['items', 'shipments']);
            DB::afterCommit(fn () => $this->notifyStatusChange($fresh, $status, $notes));

            return $fresh;
        });
    }

    public function fulfill(Order $order, array $items, ?User $actor): Order
    {
        return DB::transaction(function () use ($order, $items, $actor): Order {
            $locked = Order::query()->where('business_id', $order->business_id)->lockForUpdate()->with('items')->findOrFail($order->id);
            if (! in_array($locked->status, ['confirmed', 'processing'], true)) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'Only confirmed or processing orders can be fulfilled.', 422);
            }

            foreach ($items as $line) {
                $item = $locked->items->firstWhere('id', (int) $line['order_item_id']);
                if ($item === null) {
                    throw new ApiException('NOT_FOUND', 'An order item was not found.', 404);
                }
                $quantity = (int) $line['quantity'];
                if ($quantity < 1 || $quantity > ((int) $item->quantity - (int) $item->fulfilled_quantity)) {
                    throw new ApiException('BUSINESS_LOGIC_ERROR', 'Fulfillment quantity exceeds the unfulfilled amount.', 422);
                }
                $item->increment('fulfilled_quantity', $quantity);
            }

            $fulfilled = $locked->items()->sum('fulfilled_quantity');
            $total = $locked->items()->sum('quantity');
            $locked->update(['fulfillment_status' => $fulfilled === 0 ? 'unfulfilled' : ($fulfilled >= $total ? 'fulfilled' : 'partial')]);
            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'user_id' => $actor?->id,
                'from_status' => $locked->status,
                'to_status' => $locked->status,
                'notes' => 'Order line items fulfilled.',
                'is_customer_notified' => false,
            ]);

            return $locked->fresh(['items', 'shipments']);
        });
    }

    public function ship(Order $order, array $input, ?User $actor): Order
    {
        return DB::transaction(function () use ($order, $input, $actor): Order {
            $locked = Order::query()->where('business_id', $order->business_id)->lockForUpdate()->with('items')->findOrFail($order->id);
            if (! in_array($locked->status, ['confirmed', 'processing'], true)) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'Only confirmed or processing orders can be shipped.', 422);
            }
            $isCashOnDelivery = $locked->payments()->where('payment_method', 'cash_on_delivery')->exists();
            if (! in_array($locked->payment_status, ['authorized', 'paid', 'partially_paid'], true) && ! $isCashOnDelivery) {
                throw new ApiException('PAYMENT_FAILED', 'Payment must be authorized or confirmed before the order can ship.', 422);
            }
            $shipmentItems = $input['items'] ?? $locked->items
                ->map(fn (OrderItem $item): array => [
                    'order_item_id' => $item->id,
                    'quantity' => max(0, (int) $item->quantity - (int) $item->fulfilled_quantity),
                ])
                ->filter(fn (array $line): bool => $line['quantity'] > 0)
                ->values()
                ->all();
            if ($shipmentItems === []) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'There are no unfulfilled items to ship.', 422);
            }

            $sanitizedShipmentItems = [];
            foreach ($shipmentItems as $line) {
                $item = $locked->items->firstWhere('id', (int) $line['order_item_id']);
                $quantity = (int) $line['quantity'];
                if ($item === null || $quantity < 1 || $quantity > ((int) $item->quantity - (int) $item->fulfilled_quantity)) {
                    throw new ApiException('BUSINESS_LOGIC_ERROR', 'A shipment line exceeds the remaining quantity for its order item.', 422);
                }
                $sanitizedShipmentItems[] = ['order_item_id' => $item->id, 'quantity' => $quantity];
                $item->increment('fulfilled_quantity', $quantity);
                $item->refresh();
            }

            $shipment = OrderShipment::create([
                'order_id' => $locked->id,
                'carrier' => $input['carrier'] ?? null,
                'tracking_number' => $input['tracking_number'] ?? null,
                'tracking_url' => $input['tracking_url'] ?? null,
                'shipped_at' => now(),
                'items' => $sanitizedShipmentItems,
                'status' => 'shipped',
                'notes' => $input['notes'] ?? null,
            ]);
            $fulfilled = (int) $locked->items->sum('fulfilled_quantity');
            $total = (int) $locked->items->sum('quantity');
            $fromStatus = $locked->status;
            $locked->forceFill([
                'status' => 'shipped',
                'fulfillment_status' => $fulfilled >= $total ? 'fulfilled' : 'partial',
                'shipping_carrier' => $input['carrier'] ?? null,
                'tracking_number' => $input['tracking_number'] ?? null,
                'shipped_at' => now(),
            ])->save();
            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'user_id' => $actor?->id,
                'from_status' => $locked->getOriginal('status'),
                'to_status' => 'shipped',
                'notes' => $input['notes'] ?? null,
                'is_customer_notified' => true,
            ]);
            $fresh = $locked->fresh(['items', 'shipments']);
            DB::afterCommit(function () use ($fresh, $shipment): void {
                Mail::to($fresh->customer_email)->queue(new OrderShippedMail([
                    'name' => $fresh->customer_name,
                    'order_number' => $fresh->order_number,
                    'tracking_number' => $shipment->tracking_number,
                    'tracking_url' => $shipment->tracking_url,
                ]));
                DispatchWebhookJob::dispatch((int) $fresh->business_id, 'order.shipped', [
                    'order_number' => $fresh->order_number,
                    'tracking_number' => $shipment->tracking_number,
                ])->afterCommit();
            });

            return $fresh;
        });
    }

    public function requestReturn(Order $order, ?User $actor, array $input): OrderReturn
    {
        if (! in_array($order->status, ['delivered', 'shipped'], true)) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'A return can only be requested for a shipped or delivered order.', 422);
        }

        return OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'reason' => $input['reason'],
            'status' => 'requested',
            'refund_amount' => 0,
            'items' => $input['items'] ?? [],
            'notes' => $input['notes'] ?? null,
        ]);
    }

    public function requestRefund(Order $order, ?User $actor, array $input): Refund
    {
        if (! in_array($order->payment_status, ['paid', 'partially_paid'], true)) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'Only paid orders can be refunded.', 422);
        }
        $remaining = max(0, (float) $order->paid_amount - (float) $order->refunded_amount);
        $amount = isset($input['amount']) ? (float) $input['amount'] : $remaining;
        if ($amount <= 0 || $amount > $remaining) {
            throw new ApiException('VALIDATION_ERROR', 'The refund amount must be greater than zero and no more than the remaining paid amount.', 422);
        }

        $payment = Payment::query()->where('order_id', $order->id)->whereIn('status', ['captured', 'partially_refunded'])->latest()->first();

        return Refund::create([
            'order_id' => $order->id,
            'payment_id' => $payment?->id,
            'user_id' => $actor?->id,
            'amount' => $amount,
            'reason' => $input['reason'] ?? null,
            'status' => 'pending',
            'notes' => 'Awaiting configured payment gateway adapter or manual processing.',
        ]);
    }

    private function notifyStatusChange(Order $order, string $status, ?string $notes): void
    {
        $data = ['name' => $order->customer_name, 'order_number' => $order->order_number, 'message' => $notes];
        match ($status) {
            'cancelled' => Mail::to($order->customer_email)->queue(new OrderCancelledMail($data)),
            'delivered' => Mail::to($order->customer_email)->queue(new OrderDeliveredMail($data)),
            default => Mail::to($order->customer_email)->queue(new OrderStatusChangedMail($data + ['status' => $status])),
        };
        DispatchWebhookJob::dispatch((int) $order->business_id, 'order.'.$status, [
            'order_number' => $order->order_number,
            'status' => $status,
        ])->afterCommit();
    }
}
