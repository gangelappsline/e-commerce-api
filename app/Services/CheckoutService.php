<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Jobs\DispatchWebhookJob;
use App\Mail\NewOrderNotificationMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Business;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly InventoryService $inventory,
        private readonly GuestToken $guestTokens,
    ) {
    }

    public function placeOrder(Request $request, Business $business, array $input): Order
    {
        $order = DB::transaction(function () use ($request, $business, $input): Order {
            $cart = $this->carts->resolve($request, $business, false);
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->with(['items.product', 'items.variant', 'coupon', 'customer'])->firstOrFail();
            if ($cart->items->isEmpty()) {
                throw new ApiException('CART_EMPTY', 'Cannot checkout an empty cart.', 422);
            }
            if ($cart->customer_id === null) {
                $guestSession = $this->guestTokens->resolveSession((string) $request->header('X-Guest-Token'));
                if ($guestSession === null || $guestSession !== $cart->session_id) {
                    throw new ApiException('UNAUTHENTICATED', 'A valid guest token for this cart session is required.', 401);
                }
            }

            $requiresShipping = false;
            foreach ($cart->items as $item) {
                $product = Product::query()->forBusiness($business->id)
                    ->where('is_active', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->lockForUpdate()
                    ->findOrFail($item->product_id);
                $variant = null;
                if ($item->product_variant_id !== null) {
                    $variant = ProductVariant::query()->forBusiness($business->id)
                        ->where('product_id', $product->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->findOrFail($item->product_variant_id);
                }

                $available = $this->inventory->available($product, $variant);
                if ($product->track_inventory && ! $product->allow_backorder && $available < (int) $item->quantity) {
                    throw new ApiException('OUT_OF_STOCK', 'One or more products are no longer available in the requested quantity.', 409, ['product_id' => $product->id]);
                }
                $requiresShipping = $requiresShipping || (bool) $product->requires_shipping;
                $item->forceFill(['unit_price' => $variant?->price ?? $product->price])->save();
                $item->setRelation('product', $product);
                $item->setRelation('variant', $variant);
            }

            $cart = $this->carts->recalculate($cart->fresh(['items.product', 'items.variant', 'coupon']));
            $shippingMethod = null;
            $shippingTotal = 0.0;
            if ($requiresShipping) {
                $address = $input['shipping_address'] ?? [];
                foreach (['address_line_1', 'city', 'country'] as $requiredAddressField) {
                    if (! is_string($address[$requiredAddressField] ?? null) || trim($address[$requiredAddressField]) === '') {
                        throw new ApiException('VALIDATION_ERROR', 'A complete shipping address is required.', 422, [
                            'shipping_address.'.$requiredAddressField => ['The '.$requiredAddressField.' field is required.'],
                        ]);
                    }
                }
                if (empty($input['shipping_method_id'])) {
                    throw new ApiException('VALIDATION_ERROR', 'A shipping method is required.', 422, ['shipping_method_id' => ['The shipping method field is required.']]);
                }
                $estimates = $this->carts->estimateShipping($business, $cart, $input['shipping_address']);
                $estimate = collect($estimates)->firstWhere('id', (int) $input['shipping_method_id']);
                if ($estimate === null) {
                    throw new ApiException('BUSINESS_LOGIC_ERROR', 'The selected shipping method is not available for this address.', 422);
                }
                $shippingMethod = ShippingMethod::query()->forBusiness($business->id)
                    ->where('is_active', true)->findOrFail((int) $input['shipping_method_id']);
                $shippingTotal = $cart->coupon?->type === 'free_shipping' ? 0.0 : (float) $estimate['price'];
            }

            $coupon = null;
            if ($cart->coupon_id !== null) {
                $coupon = Coupon::query()->forBusiness($business->id)->whereKey($cart->coupon_id)->lockForUpdate()->first();
                if ($coupon === null) {
                    throw new ApiException('BUSINESS_LOGIC_ERROR', 'The coupon in this cart is no longer valid.', 422);
                }
                $this->carts->validateCouponForCart($cart, $business, $coupon);
            }

            $taxTotal = (float) $cart->tax_total;
            $discountTotal = (float) $cart->discount_total;
            $grandTotal = max(0, round((float) $cart->subtotal - $discountTotal + $taxTotal + $shippingTotal, 2));
            $user = $request->user('api');
            $guestSessionId = $cart->customer_id === null ? $cart->session_id : null;
            $orderNumber = 'ORD-'.strtoupper(Str::random(10));

            $order = Order::create([
                'business_id' => $business->id,
                'customer_id' => $cart->customer_id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'payment_status' => 'pending',
                'fulfillment_status' => 'unfulfilled',
                'currency' => $business->currency,
                'subtotal' => $cart->subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => 0,
                'refunded_amount' => 0,
                'customer_email' => mb_strtolower($input['customer_email']),
                'customer_phone' => $input['customer_phone'] ?? null,
                'customer_name' => $input['customer_name'],
                'shipping_address' => $input['shipping_address'],
                'billing_address' => $input['billing_address'] ?? $input['shipping_address'],
                'shipping_method' => $shippingMethod?->name,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'notes' => $input['notes'] ?? null,
                'source' => $input['source'] ?? 'web',
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 4000),
                'guest_session_id' => $guestSessionId,
                'placed_at' => now(),
            ]);

            $itemSum = 0.0;
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                $variant = $cartItem->variant;
                if ($product === null) {
                    throw new ApiException('BUSINESS_LOGIC_ERROR', 'A product in this cart is no longer available.', 422);
                }
                $unitPrice = (float) ($variant?->price ?? $product->price);
                $lineSubtotal = round($unitPrice * (int) $cartItem->quantity, 2);
                $lineDiscount = (float) $cartItem->discount;
                $lineTax = (float) $cartItem->tax;
                $lineTotal = round(max(0, $lineSubtotal - $lineDiscount + $lineTax), 2);
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'product_sku' => $variant?->sku ?? $product->sku,
                    'variant_name' => $variant?->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $cartItem->quantity,
                    'discount' => $lineDiscount,
                    'tax' => $lineTax,
                    'subtotal' => $lineSubtotal,
                    'total' => $lineTotal,
                    'metadata' => ['product_slug' => $product->slug],
                ]);
                $this->inventory->decreaseForOrderItem($product, $variant, (int) $cartItem->quantity, $orderItem, $user instanceof User ? $user : null);
                $itemSum += $lineTotal;
            }

            // Rounding the distributed discount/tax can differ by a cent; persist the authoritative cart totals.
            $order->forceFill(['grand_total' => round($itemSum + $shippingTotal, 2)])->save();
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $user instanceof User ? $user->id : null,
                'from_status' => null,
                'to_status' => 'pending',
                'notes' => 'Order placed through the storefront API.',
                'is_customer_notified' => false,
            ]);

            Payment::create([
                'business_id' => $business->id,
                'order_id' => $order->id,
                'customer_id' => $cart->customer_id,
                'payment_method' => $input['payment_method'] ?? 'other',
                'payment_gateway' => null,
                'amount' => $order->grand_total,
                'currency' => $business->currency,
                'status' => 'pending',
                'gateway_response' => ['state' => 'awaiting_gateway_or_manual_confirmation'],
            ]);

            if ($coupon !== null) {
                $coupon->increment('used_count');
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'customer_id' => $cart->customer_id,
                    'order_id' => $order->id,
                    'discount_amount' => $discountTotal,
                    'used_at' => now(),
                ]);
            }

            $cart->items()->delete();
            $cart->delete();

            return $order->load(['items', 'shipments', 'payments']);
        });

        DB::afterCommit(function () use ($order, $business): void {
            Mail::to($order->customer_email)->queue(new OrderConfirmationMail([
                'name' => $order->customer_name,
                'order_number' => $order->order_number,
                'store_name' => $business->name,
            ]));
            $recipient = $business->email ?: $business->owner?->email;
            if (is_string($recipient) && $recipient !== '') {
                Mail::to($recipient)->queue(new NewOrderNotificationMail([
                    'name' => $business->owner?->name ?? $business->name,
                    'order_number' => $order->order_number,
                    'message' => 'A new order is waiting for review.',
                ]));
            }
            DispatchWebhookJob::dispatch((int) $business->id, 'order.created', [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'grand_total' => $order->grand_total,
                'currency' => $order->currency,
            ])->afterCommit();
        });

        return $order;
    }
}
