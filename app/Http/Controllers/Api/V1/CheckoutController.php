<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Models\Business;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class CheckoutController extends BaseController
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/checkout',
        tags: ['Checkout'],
        summary: 'Create a pending order from the current cart',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['customer_name', 'customer_email'], properties: [
            new OA\Property(property: 'customer_name', type: 'string', example: 'Alex Rivera'),
            new OA\Property(property: 'customer_email', type: 'string', format: 'email', example: 'alex@example.com'),
            new OA\Property(property: 'shipping_method_id', type: 'integer', nullable: true, example: 3),
            new OA\Property(property: 'payment_method', type: 'string', example: 'cash_on_delivery'),
            new OA\Property(property: 'shipping_address', type: 'object', example: ['address_line_1' => '42 Market Street', 'city' => 'Austin', 'country' => 'US']),
        ])),
        responses: [new OA\Response(response: 201, description: 'Order created; payment remains pending until an adapter confirms it', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess')), new OA\Response(response: 409, description: 'Inventory conflict', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))],
    )]
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'customer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'shipping_method_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'payment_method' => ['sometimes', 'string', 'in:credit_card,debit_card,paypal,cash_on_delivery,bank_transfer,wallet,other'],
            'shipping_address' => ['sometimes', 'nullable', 'array'],
            'shipping_address.first_name' => ['sometimes', 'string', 'max:100'],
            'shipping_address.last_name' => ['sometimes', 'string', 'max:100'],
            'shipping_address.phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'shipping_address.address_line_1' => ['sometimes', 'string', 'max:255'],
            'shipping_address.address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipping_address.city' => ['sometimes', 'string', 'max:100'],
            'shipping_address.state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'shipping_address.country' => ['sometimes', 'string', 'max:100'],
            'shipping_address.postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'billing_address' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'source' => ['sometimes', 'in:web,mobile,pos,api'],
        ]);

        $user = $request->user('api');
        $data['customer_name'] = $data['customer_name'] ?? ($user instanceof User ? $user->name : null);
        $data['customer_email'] = mb_strtolower((string) ($data['customer_email'] ?? ($user instanceof User ? $user->email : '')));
        if (! is_string($data['customer_name']) || $data['customer_name'] === '' || $data['customer_email'] === '') {
            throw new ApiException('VALIDATION_ERROR', 'Customer name and email are required for checkout.', 422, [
                'customer_name' => ['The customer name field is required.'],
                'customer_email' => ['The customer email field is required.'],
            ]);
        }

        $shippingAddress = $data['shipping_address'] ?? null;
        if ((! is_array($shippingAddress) || $shippingAddress === []) && $user instanceof User) {
            $defaultAddress = $user->addresses()->where('is_default_shipping', true)->first();
            if ($defaultAddress instanceof UserAddress) {
                $shippingAddress = $this->addressSnapshot($defaultAddress);
            }
        }
        $data['shipping_address'] = is_array($shippingAddress) ? $shippingAddress : [];
        $data['billing_address'] = $data['billing_address'] ?? $data['shipping_address'];
        $data['source'] = $data['source'] ?? 'web';

        $order = $this->checkout->placeOrder($request, $this->business($request), $data);
        $order->load(['items', 'shipments', 'payments']);
        $payment = $order->payments->first();

        return $this->successResponse([
            'order' => (new OrderResource($order))->resolve($request),
            'payment' => $payment?->only(['id', 'payment_method', 'payment_gateway', 'amount', 'currency', 'status']),
            'payment_action_required' => $payment !== null && $payment->status === 'pending',
        ], 'Order placed successfully. Payment is pending confirmation.', 201);
    }

    public function calculateTotals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['sometimes', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'shipping_method_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $business = $this->business($request);
        $cart = $this->carts->resolve($request, $business, false)->load(['items.product', 'items.variant', 'coupon']);
        $this->carts->recalculate($cart);
        $shippingOptions = [];
        if (isset($data['country'])) {
            $shippingOptions = $this->carts->estimateShipping($business, $cart, $data);
        }
        if (! empty($data['shipping_method_id'])) {
            $estimate = collect($shippingOptions)->firstWhere('id', (int) $data['shipping_method_id']);
            if ($estimate === null) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'The selected shipping method is not available for this address.', 422);
            }
            $cart->forceFill([
                'shipping_total' => $cart->coupon?->type === 'free_shipping' ? 0 : (float) $estimate['price'],
            ])->save();
            $cart = $this->carts->recalculate($cart->fresh(['items.product', 'items.variant', 'coupon']));
        }

        return $this->successResponse([
            'cart' => (new CartResource($cart))->resolve($request),
            'shipping_options' => $shippingOptions,
        ], 'Checkout totals calculated successfully.');
    }

    private function addressSnapshot(UserAddress $address): array
    {
        return $address->only([
            'first_name', 'last_name', 'phone', 'address_line_1', 'address_line_2', 'city', 'state', 'country', 'postal_code',
        ]);
    }

    private function business(Request $request): Business
    {
        $business = $request->attributes->get('tenant');
        if (! $business instanceof Business) {
            throw new ApiException('STORE_NOT_FOUND', 'Store context is required.', 404);
        }

        return $business;
    }
}
