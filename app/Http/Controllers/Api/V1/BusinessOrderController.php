<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\OrderResource;
use App\Models\Business;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderLifecycleService;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BusinessOrderController extends BaseController
{
    public function __construct(private readonly OrderLifecycleService $lifecycle)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $query = Order::query()->forBusiness($business->id)->with(['items', 'shipments']);
        if ($request->filled('status')) {
            $request->validate(['status' => ['required', 'in:pending,confirmed,processing,shipped,delivered,cancelled,refunded,returned']]);
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('payment_status')) {
            $request->validate(['payment_status' => ['required', 'in:pending,authorized,paid,partially_paid,refunded,failed']]);
            $query->where('payment_status', $request->query('payment_status'));
        }
        if ($request->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_substr((string) $request->query('q'), 0, 120)).'%';
            $query->where(fn ($builder) => $builder->where('order_number', 'like', $term)->orWhere('customer_email', 'like', $term)->orWhere('customer_name', 'like', $term));
        }
        if ($request->filled('from')) {
            $request->validate(['from' => ['required', 'date']]);
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $request->validate(['to' => ['required', 'date']]);
            $query->whereDate('created_at', '<=', $request->query('to'));
        }
        $paginator = $query->latest('created_at')->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return $this->paginatedResponse($paginator, 'Business orders retrieved successfully.', fn (Order $order): array => (new OrderResource($order))->resolve($request));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = $this->order($request, $id)->load(['items', 'shipments', 'statusHistory']);

        return $this->successResponse(new OrderResource($order), 'Business order retrieved successfully.');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:confirmed,processing,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $order = $this->lifecycle->changeStatus($this->order($request, $id), $data['status'], $request->user('api'), $data['notes'] ?? null);

        return $this->successResponse(new OrderResource($order), 'Order status updated successfully.');
    }

    public function fulfill(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $order = $this->lifecycle->fulfill($this->order($request, $id), $data['items'], $request->user('api'));

        return $this->successResponse(new OrderResource($order), 'Order fulfillment updated successfully.');
    }

    public function ship(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'carrier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:150'],
            'tracking_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.order_item_id' => ['required_with:items', 'integer', 'distinct'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $order = $this->lifecycle->ship($this->order($request, $id), $data, $request->user('api'));

        return $this->successResponse(new OrderResource($order), 'Order marked as shipped successfully.');
    }

    public function deliver(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['notes' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $order = $this->lifecycle->changeStatus($this->order($request, $id), 'delivered', $request->user('api'), $data['notes'] ?? null);

        return $this->successResponse(new OrderResource($order), 'Order marked as delivered successfully.');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:1000']]);
        $order = $this->lifecycle->changeStatus($this->order($request, $id), 'cancelled', $request->user('api'), $data['reason'] ?? 'Cancelled by store staff.');

        return $this->successResponse(new OrderResource($order), 'Order cancelled successfully.');
    }

    public function markPaid(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:180'],
            'confirmation_reference' => ['required', 'string', 'max:180'],
        ]);
        $business = $this->business($request);
        $payment = DB::transaction(function () use ($request, $id, $data, $business): Payment {
            $order = Order::query()->forBusiness($business->id)->lockForUpdate()->findOrFail($id);
            $payment = Payment::query()->where('business_id', $business->id)->where('order_id', $order->id)
                ->where('status', 'pending')->lockForUpdate()->latest()->firstOrFail();
            if (! in_array($payment->payment_method, ['cash_on_delivery', 'bank_transfer', 'other'], true)) {
                throw new ApiException('PAYMENT_FAILED', 'This payment method requires a configured gateway adapter and cannot be manually captured.', 422);
            }
            if ($payment->payment_method === 'cash_on_delivery' && $order->status !== 'delivered') {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'Cash-on-delivery payments can only be recorded after delivery.', 422);
            }
            $amount = (float) $payment->amount;
            $newPaid = (float) $order->paid_amount + $amount;
            $payment->forceFill([
                'status' => 'captured',
                'transaction_id' => $data['transaction_id'] ?? $data['confirmation_reference'],
                'gateway_response' => ['manual_confirmation_reference' => $data['confirmation_reference']],
                'paid_at' => now(),
            ])->save();
            $order->forceFill([
                'paid_amount' => $newPaid,
                'payment_status' => $newPaid >= (float) $order->grand_total ? 'paid' : 'partially_paid',
            ])->save();

            return $payment;
        });

        return $this->successResponse($payment->only(['id', 'order_id', 'payment_method', 'transaction_id', 'amount', 'currency', 'status', 'paid_at']), 'Manual payment confirmation recorded successfully.');
    }

    public function refund(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $order = $this->order($request, $id);
        $refund = $this->lifecycle->requestRefund($order, $request->user('api'), $data);

        return $this->successResponse($refund->only(['id', 'order_id', 'payment_id', 'amount', 'reason', 'status', 'created_at']), 'Refund request submitted for processing.', 202);
    }

    public function addNotes(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:5000']]);
        $order = $this->order($request, $id);
        $old = $order->internal_notes;
        $order->forceFill(['internal_notes' => trim(implode("\n\n", array_filter([$old, $data['notes']])) )])->save();
        DB::table('audit_logs')->insert([
            'business_id' => $order->business_id,
            'user_id' => $request->user('api')?->id,
            'action' => 'order.note_added',
            'model_type' => Order::class,
            'model_id' => $order->id,
            'old_values' => json_encode(['internal_notes' => $old], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['internal_notes' => $order->internal_notes], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 4000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(['internal_notes' => $order->internal_notes], 'Internal note added successfully.');
    }

    public function timeline(Request $request, string $id): JsonResponse
    {
        $order = $this->order($request, $id);
        $history = OrderStatusHistory::query()->where('order_id', $order->id)->with('user:id,name')->orderBy('created_at')->get()
            ->map(fn (OrderStatusHistory $entry): array => [
                'id' => $entry->id,
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'notes' => $entry->notes,
                'actor' => $entry->user?->only(['id', 'name']),
                'is_customer_notified' => $entry->is_customer_notified,
                'created_at' => $entry->created_at,
            ]);

        return $this->successResponse($history, 'Order timeline retrieved successfully.');
    }

    private function order(Request $request, string $id): Order
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'Order not found.', 404);
        }
        $business = $this->business($request);

        return Order::query()->forBusiness($business->id)->findOrFail((int) $id);
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
