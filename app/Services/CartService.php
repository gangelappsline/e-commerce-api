<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\CustomerBusiness;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CartService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly GuestToken $guestTokens,
    ) {
    }

    public function resolve(Request $request, Business $business, bool $create = true): Cart
    {
        $user = $request->user('api');
        if ($user instanceof User) {
            $customer = $this->customerForUser($user, $business);
            $cartQuery = Cart::query()->where('business_id', $business->id)->where('customer_id', $customer->id);
        } else {
            $sessionId = $this->guestSession($request);
            $cartQuery = Cart::query()->where('business_id', $business->id)->where('session_id', $sessionId);
        }

        $cart = $cartQuery->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->with(['items.product.business', 'items.variant', 'coupon'])->latest('id')->first();

        if ($cart !== null) {
            return $cart;
        }
        if (! $create) {
            throw new ApiException('CART_EMPTY', 'No active cart was found.', 404);
        }

        $identity = $user instanceof User
            ? ['customer_id' => $customer->id, 'session_id' => null]
            : ['customer_id' => null, 'session_id' => $sessionId];

        return Cart::create([
            'business_id' => $business->id,
            ...$identity,
            'currency' => $business->currency,
            'expires_at' => now()->addDays(30),
        ])->load('items');
    }

    public function addItem(Request $request, Business $business, array $data): Cart
    {
        return DB::transaction(function () use ($request, $business, $data): Cart {
            $cart = $this->resolve($request, $business);
            $product = Product::query()->forBusiness($business->id)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->lockForUpdate()
                ->findOrFail((int) $data['product_id']);
            $variant = null;
            if (! empty($data['product_variant_id'])) {
                $variant = ProductVariant::query()->forBusiness($business->id)
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail((int) $data['product_variant_id']);
            }

            $quantity = (int) $data['quantity'];
            $existingQuery = CartItem::query()->where('cart_id', $cart->id)->where('product_id', $product->id);
            $variant === null ? $existingQuery->whereNull('product_variant_id') : $existingQuery->where('product_variant_id', $variant->id);
            $existing = $existingQuery->lockForUpdate()->first();
            $newQuantity = $quantity + (int) ($existing?->quantity ?? 0);
            if ($newQuantity > 1000) {
                throw new ApiException('VALIDATION_ERROR', 'The requested quantity exceeds the per-item limit.', 422, ['quantity' => ['The requested quantity may not exceed 1000.']]);
            }

            if ($product->track_inventory && ! $product->allow_backorder && $this->inventory->available($product, $variant) < $newQuantity) {
                throw new ApiException('OUT_OF_STOCK', 'The requested quantity is not currently available.', 409, ['product_id' => $product->id]);
            }

            $unitPrice = (float) ($variant?->price ?? $product->price);
            if ($existing !== null) {
                $existing->update(['quantity' => $newQuantity, 'unit_price' => $unitPrice]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $newQuantity,
                    'unit_price' => $unitPrice,
                    'metadata' => [],
                ]);
            }

            return $this->recalculate($cart->fresh(['items.product', 'items.variant', 'coupon']));
        });
    }

    public function updateItem(Request $request, Business $business, string $itemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($request, $business, $itemId, $quantity): Cart {
            $cart = $this->resolve($request, $business, false);
            $item = $cart->items()->with(['product', 'variant'])->lockForUpdate()->findOrFail($itemId);
            if ($quantity === 0) {
                $item->delete();
            } else {
                if ($quantity > 1000) {
                    throw new ApiException('VALIDATION_ERROR', 'The requested quantity exceeds the per-item limit.', 422);
                }
                if ($item->product->track_inventory && ! $item->product->allow_backorder
                    && $this->inventory->available($item->product, $item->variant) < $quantity) {
                    throw new ApiException('OUT_OF_STOCK', 'The requested quantity is not currently available.', 409);
                }
                $item->update(['quantity' => $quantity]);
            }

            return $this->recalculate($cart->fresh(['items.product', 'items.variant', 'coupon']));
        });
    }

    public function removeItem(Request $request, Business $business, string $itemId): Cart
    {
        $cart = $this->resolve($request, $business, false);
        $cart->items()->whereKey($itemId)->delete();

        return $this->recalculate($cart->fresh(['items.product', 'items.variant', 'coupon']));
    }

    public function clear(Request $request, Business $business): void
    {
        $this->resolve($request, $business, false)->delete();
    }

    public function applyCoupon(Request $request, Business $business, string $code): Cart
    {
        return DB::transaction(function () use ($request, $business, $code): Cart {
            $cart = $this->resolve($request, $business, false)->load(['items.product', 'items.variant']);
            $coupon = Coupon::query()->forBusiness($business->id)->whereRaw('UPPER(code) = ?', [mb_strtoupper($code)])
                ->where('is_active', true)
                ->where(function ($query): void { $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
                ->where(function ($query): void { $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()); })
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateCouponForCart($cart, $business, $coupon);
            $cart->forceFill(['coupon_id' => $coupon->id])->save();

            return $this->recalculate($cart->fresh(['items.product', 'items.variant', 'coupon']));
        });
    }

    public function removeCoupon(Request $request, Business $business): Cart
    {
        $cart = $this->resolve($request, $business, false);
        $cart->forceFill(['coupon_id' => null])->save();

        return $this->recalculate($cart->fresh(['items.product', 'items.variant', 'coupon']));
    }

    public function validateCouponForCart(Cart $cart, Business $business, Coupon $coupon): void
    {
        if ((int) $cart->business_id !== (int) $business->id || (int) $coupon->business_id !== (int) $business->id) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The coupon is not available for this store.', 422);
        }
        if (! $coupon->is_active || ($coupon->starts_at !== null && $coupon->starts_at->isFuture())
            || ($coupon->expires_at !== null && $coupon->expires_at->isPast())) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'This coupon is not currently available.', 422);
        }
        if ($coupon->type === 'buy_x_get_y') {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'Buy X Get Y coupons are not enabled for this checkout channel.', 422);
        }

        $customer = $cart->customer;
        $subtotal = (float) $cart->items->sum(fn (CartItem $item): float => (float) $item->unit_price * (int) $item->quantity);
        if ($coupon->minimum_order_amount !== null && $subtotal < (float) $coupon->minimum_order_amount) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The cart does not meet the minimum order amount for this coupon.', 422);
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'This coupon has reached its usage limit.', 422);
        }
        if ($coupon->customer_eligibility === 'new_customers') {
            if ($customer === null || Order::query()->forBusiness($business->id)->where('customer_id', $customer->id)
                ->where('status', '!=', 'cancelled')->exists()) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'This coupon is available to new customers only.', 422);
            }
        }
        if ($coupon->customer_eligibility === 'specific_customers'
            && ($customer === null || ! in_array((int) $customer->id, array_map('intval', $coupon->eligible_customer_ids ?? []), true))) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'This coupon is not available to this customer.', 422);
        }
        if ($coupon->usage_limit_per_customer !== null) {
            if ($customer === null) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'Sign in before using this customer-limited coupon.', 422);
            }
            $uses = CouponUsage::query()->where('coupon_id', $coupon->id)->where('customer_id', $customer->id)->count();
            if ($uses >= $coupon->usage_limit_per_customer) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'This customer has already used this coupon the maximum number of times.', 422);
            }
        }
        if ($this->eligibleSubtotal($cart, $coupon) <= 0) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'This coupon does not apply to the items in the cart.', 422);
        }
    }

    public function recalculate(Cart $cart): Cart
    {
        $cart->loadMissing(['business', 'items.product.business', 'items.variant', 'coupon']);
        $subtotal = (float) $cart->items->sum(fn (CartItem $item): float => round((float) $item->unit_price * (int) $item->quantity, 2));
        $shipping = (float) $cart->shipping_total;
        $coupon = $cart->coupon;
        $eligibleSubtotal = 0.0;
        $productDiscount = 0.0;
        $shippingDiscount = 0.0;

        if ($coupon !== null && $coupon->is_active
            && ($coupon->starts_at === null || $coupon->starts_at->isPast())
            && ($coupon->expires_at === null || $coupon->expires_at->isFuture())) {
            $eligibleSubtotal = $this->eligibleSubtotal($cart, $coupon);
            $productDiscount = match ($coupon->type) {
                'percentage' => round($eligibleSubtotal * min(100, (float) $coupon->value) / 100, 2),
                'fixed_amount' => min($eligibleSubtotal, (float) $coupon->value),
                default => 0.0,
            };
            $shippingDiscount = $coupon->type === 'free_shipping' ? $shipping : 0.0;
            if ($coupon->maximum_discount !== null) {
                $productDiscount = min($productDiscount, (float) $coupon->maximum_discount);
            }
        }

        $eligibleItemIds = $coupon === null || $coupon->type === 'free_shipping'
            ? []
            : $cart->items->filter(fn (CartItem $item): bool => $this->isCouponEligible($item, $coupon))->pluck('id')->values();
        $lastEligibleItemId = $eligibleItemIds->last();
        $remainingDiscount = $productDiscount;
        $taxTotal = 0.0;
        foreach ($cart->items as $item) {
            $lineSubtotal = round((float) $item->unit_price * (int) $item->quantity, 2);
            $eligible = $coupon === null || $coupon->type === 'free_shipping' || $eligibleItemIds->contains($item->id);
            $lineDiscount = 0.0;
            if ($eligible && $eligibleSubtotal > 0 && $productDiscount > 0) {
                $lineDiscount = (int) $item->id === (int) $lastEligibleItemId
                    ? min($lineSubtotal, $remainingDiscount)
                    : min($lineSubtotal, round($productDiscount * $lineSubtotal / $eligibleSubtotal, 2));
                $lineDiscount = min($lineDiscount, $remainingDiscount);
                $remainingDiscount = round($remainingDiscount - $lineDiscount, 2);
            }
            $productRate = $item->product?->tax_rate;
            $rate = $productRate !== null
                ? (float) $productRate
                : (float) ($item->product?->business?->tax_rate ?? $cart->business?->tax_rate ?? 0);
            $productTaxIncluded = $item->product?->tax_included;
            $taxIncluded = $productTaxIncluded !== null
                ? (bool) $productTaxIncluded
                : (bool) ($item->product?->business?->tax_included ?? $cart->business?->tax_included ?? false);
            $lineTax = $taxIncluded ? 0.0 : round(max(0, $lineSubtotal - $lineDiscount) * $rate / 100, 2);
            $item->forceFill([
                'subtotal' => $lineSubtotal,
                'discount' => $lineDiscount,
                'tax' => $lineTax,
                'total' => round(max(0, $lineSubtotal - $lineDiscount + $lineTax), 2),
            ])->save();
            $taxTotal += $lineTax;
        }

        $discount = min($subtotal, $productDiscount) + $shippingDiscount;

        return tap($cart)->forceFill([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round(max(0, $subtotal - $discount + $taxTotal + $shipping), 2),
        ])->save();
    }

    public function estimateShipping(Business $business, Cart $cart, array $address): array
    {
        $zones = ShippingZone::query()->forBusiness($business->id)->where('is_active', true)->get();
        $weight = (float) $cart->items->sum(fn (CartItem $item): float => (float) ($item->product?->weight ?? 0) * (int) $item->quantity);
        $matchingZoneIds = $zones->filter(fn (ShippingZone $zone): bool => $this->zoneMatches($zone, $address))->pluck('id')->all();
        $methods = ShippingMethod::query()->forBusiness($business->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhereIn('zone_id', $matchingZoneIds))
            ->orderBy('sort_order')->get();
        $estimates = [];

        foreach ($methods as $method) {
            if ($method->min_order_amount !== null && (float) $cart->subtotal < (float) $method->min_order_amount) {
                continue;
            }
            if ($method->max_order_amount !== null && (float) $cart->subtotal > (float) $method->max_order_amount) {
                continue;
            }
            $price = match ($method->type) {
                'free' => 0.0,
                'weight_based' => round((float) $method->price * max(1, $weight), 2),
                default => (float) $method->price,
            };
            $estimates[] = [
                'id' => $method->id,
                'name' => $method->name,
                'type' => $method->type,
                'price' => number_format($price, 2, '.', ''),
                'currency' => $business->currency,
                'estimated_days_min' => $method->estimated_days_min,
                'estimated_days_max' => $method->estimated_days_max,
            ];
        }

        return $estimates;
    }

    public function mergeGuestCart(Request $request, Business $business, string $sessionId): Cart
    {
        if (! Str::isUuid($sessionId)) {
            throw new ApiException('VALIDATION_ERROR', 'A valid guest cart session is required.', 422);
        }
        $tokenSession = $this->guestTokens->resolveSession((string) $request->header('X-Guest-Token'));
        if ($tokenSession === null || ! hash_equals($sessionId, $tokenSession)) {
            throw new ApiException('UNAUTHORIZED', 'A valid guest token for this cart session is required.', 403);
        }
        $user = $request->user('api');
        if (! $user instanceof User) {
            throw new ApiException('UNAUTHENTICATED', 'Authentication is required to merge a guest cart.', 401);
        }

        return DB::transaction(function () use ($request, $business, $sessionId, $user): Cart {
            $guestCart = Cart::query()->forBusiness($business->id)->where('session_id', $sessionId)->with(['items.product', 'items.variant', 'coupon'])->lockForUpdate()->first();
            if ($guestCart === null) {
                throw new ApiException('CART_EMPTY', 'No guest cart was found for this session.', 404);
            }
            $target = $this->resolve($request, $business);
            foreach ($guestCart->items as $guestItem) {
                $query = $target->items()->where('product_id', $guestItem->product_id);
                $guestItem->product_variant_id === null ? $query->whereNull('product_variant_id') : $query->where('product_variant_id', $guestItem->product_variant_id);
                $existing = $query->lockForUpdate()->first();
                $quantity = min(1000, (int) $guestItem->quantity + (int) ($existing?->quantity ?? 0));
                if ($existing !== null) {
                    $existing->update(['quantity' => $quantity]);
                } else {
                    $guestItem->replicate()->fill(['cart_id' => $target->id, 'quantity' => $quantity])->save();
                }
            }
            $guestCart->delete();

            return $this->recalculate($target->fresh(['items.product', 'items.variant', 'coupon']));
        });
    }

    private function customerForUser(User $user, Business $business): Customer
    {
        $customer = $user->customer()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'default_business_id' => $business->id,
        ]);
        $membership = CustomerBusiness::query()->firstOrCreate([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
        ]);
        if ($membership->is_blocked) {
            throw new ApiException('UNAUTHORIZED', 'This customer is blocked from purchasing in this store.', 403);
        }

        return $customer;
    }

    private function guestSession(Request $request): string
    {
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');
        $guestToken = $request->header('X-Guest-Token');

        if ($guestToken !== null) {
            $tokenSession = $this->guestTokens->resolveSession((string) $guestToken);
            if ($tokenSession === null) {
                throw new ApiException('UNAUTHENTICATED', 'The supplied guest token is invalid or expired.', 401);
            }
            if ($sessionId === null) {
                $sessionId = $tokenSession;
            } elseif (! is_string($sessionId) || ! hash_equals($tokenSession, $sessionId)) {
                throw new ApiException('UNAUTHORIZED', 'The guest token does not match this cart session.', 403);
            }
        }

        if (! is_string($sessionId) || ! Str::isUuid($sessionId)) {
            throw new ApiException('UNAUTHENTICATED', 'Provide a valid X-Cart-Session or guest token.', 401);
        }

        return $sessionId;
    }

    private function eligibleSubtotal(Cart $cart, Coupon $coupon): float
    {
        return (float) $cart->items
            ->filter(fn (CartItem $item): bool => $this->isCouponEligible($item, $coupon))
            ->sum(fn (CartItem $item): float => round((float) $item->unit_price * (int) $item->quantity, 2));
    }

    private function isCouponEligible(CartItem $item, Coupon $coupon): bool
    {
        $ids = array_map('intval', $coupon->applies_to_ids ?? []);
        if ($coupon->applies_to === 'all') {
            return true;
        }
        if ($ids === []) {
            return false;
        }

        return match ($coupon->applies_to) {
            'specific_products' => in_array((int) $item->product_id, $ids, true),
            'specific_categories' => in_array((int) ($item->product?->category_id ?? 0), $ids, true),
            'specific_collections' => DB::table('collection_product')
                ->join('collections', 'collections.id', '=', 'collection_product.collection_id')
                ->where('collections.business_id', $coupon->business_id)
                ->where('collection_product.product_id', $item->product_id)
                ->whereIn('collection_product.collection_id', $ids)
                ->exists(),
            default => false,
        };
    }

    private function zoneMatches(ShippingZone $zone, array $address): bool
    {
        $country = mb_strtolower((string) ($address['country'] ?? ''));
        $state = mb_strtolower((string) ($address['state'] ?? ''));
        $postal = mb_strtolower((string) ($address['postal_code'] ?? ''));
        $countries = array_map('mb_strtolower', $zone->countries ?? []);
        if ($countries !== [] && ! in_array($country, $countries, true)) {
            return false;
        }
        $states = array_map('mb_strtolower', $zone->states ?? []);
        if ($states !== [] && ! in_array($state, $states, true)) {
            return false;
        }
        $postalCodes = array_map('mb_strtolower', $zone->postal_codes ?? []);
        if ($postalCodes !== [] && ! in_array($postal, $postalCodes, true)) {
            return false;
        }

        return true;
    }
}
