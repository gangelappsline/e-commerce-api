<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\OrderResource;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use App\Services\GuestToken;
use App\Services\OrderLifecycleService;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderController extends BaseController
{
    public function __construct(
        private readonly OrderLifecycleService $lifecycle,
        private readonly GuestToken $guestTokens,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $user = $request->user('api');
        if ($user instanceof User) {
            $customer = Customer::query()->where('user_id', $user->id)->first();
            if ($customer === null) {
                return $this->paginatedResponse(Order::query()->whereRaw('1 = 0')->paginate(15), 'Orders retrieved successfully.');
            }
            $query = Order::query()->forBusiness($business->id)->where('customer_id', $customer->id);
        } else {
            $sessionId = $this->guestTokens->resolveSession((string) $request->header('X-Guest-Token'));
            if ($sessionId === null) {
                throw new ApiException('UNAUTHENTICATED', 'A valid Passport or guest token is required.', 401);
            }
            $query = Order::query()->forBusiness($business->id)->where('guest_session_id', $sessionId);
        }

        $paginator = $query->with(['items', 'shipments'])->latest('created_at')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($paginator, 'Orders retrieved successfully.', fn (Order $order): array => (new OrderResource($order))->resolve($request));
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->authorizedOrder($request, $orderNumber);

        return $this->successResponse(new OrderResource($order), 'Order retrieved successfully.');
    }

    public function tracking(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->authorizedOrder($request, $orderNumber);

        return $this->successResponse([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status,
            'shipping_carrier' => $order->shipping_carrier,
            'tracking_number' => $order->tracking_number,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'shipments' => $order->shipments->map(fn ($shipment): array => $shipment->only([
                'carrier', 'tracking_number', 'tracking_url', 'status', 'shipped_at', 'delivered_at',
            ])),
        ], 'Order tracking retrieved successfully.');
    }

    public function checkoutStatus(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->authorizedOrder($request, $orderNumber);

        return $this->successResponse([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'updated_at' => $order->updated_at,
        ], 'Checkout status retrieved successfully.');
    }

    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:1000']]);
        $order = $this->authorizedOrder($request, $orderNumber);
        $updated = $this->lifecycle->changeStatus($order, 'cancelled', $request->user('api'), $data['reason'] ?? 'Cancelled by customer.');

        return $this->successResponse(new OrderResource($updated), 'Order cancelled successfully.');
    }

    public function requestReturn(Request $request, string $orderNumber): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'items' => ['sometimes', 'array', 'max:100'],
            'items.*.order_item_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $order = $this->authorizedOrder($request, $orderNumber);
        $return = $this->lifecycle->requestReturn($order, $request->user('api'), $data);

        return $this->successResponse($return->only(['id', 'order_id', 'reason', 'status', 'items', 'created_at']), 'Return request submitted successfully.', 201);
    }

    private function authorizedOrder(Request $request, string $orderNumber): Order
    {
        $business = $this->business($request);
        $order = Order::query()->forBusiness($business->id)->with(['items', 'shipments'])->where('order_number', $orderNumber)->firstOrFail();
        $user = $request->user('api');
        if ($user instanceof User) {
            $customerId = Customer::query()->where('user_id', $user->id)->value('id');
            if ($customerId === null || (int) $order->customer_id !== (int) $customerId) {
                throw new ApiException('UNAUTHORIZED', 'You are not allowed to view this order.', 403);
            }

            return $order;
        }

        $sessionId = $this->guestTokens->resolveSession((string) $request->header('X-Guest-Token'));
        if ($sessionId === null || $order->guest_session_id === null || ! hash_equals($order->guest_session_id, $sessionId)) {
            throw new ApiException('UNAUTHORIZED', 'A valid guest token for this order is required.', 403);
        }

        return $order;
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
