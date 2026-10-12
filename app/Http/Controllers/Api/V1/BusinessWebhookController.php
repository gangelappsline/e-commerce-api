<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Models\Webhook;
use App\Services\WebhookUrlValidator;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class BusinessWebhookController extends BaseController
{
    private const EVENTS = [
        'order.created', 'order.confirmed', 'order.processing', 'order.shipped', 'order.delivered',
        'order.cancelled', 'order.refunded', 'product.created', 'product.updated', 'inventory.low_stock',
        'review.created', 'customer.created',
    ];

    public function index(Request $request): JsonResponse
    {
        $webhooks = Webhook::query()->forBusiness($this->business($request)->id)->latest()->get();

        return $this->successResponse($webhooks->map(fn (Webhook $webhook): array => $webhook->only([
            'id', 'event', 'url', 'is_active', 'last_triggered_at', 'failure_count', 'created_at',
        ])), 'Webhooks retrieved successfully.');
    }

    public function store(Request $request, WebhookUrlValidator $validator): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(self::EVENTS)],
            'url' => ['required', 'url', 'max:2048'],
        ]);
        if (! $validator->isSafeHttpsUrl($data['url'])) {
            throw new ApiException('VALIDATION_ERROR', 'Webhook URL must resolve to a public HTTPS destination.', 422, ['url' => ['The supplied URL is not an allowed webhook destination.']]);
        }
        $secret = Str::random(64);
        $webhook = Webhook::create([
            'business_id' => $this->business($request)->id,
            'event' => $data['event'],
            'url' => $data['url'],
            'secret' => $secret,
            'is_active' => true,
        ]);

        return $this->successResponse([
            ...$webhook->only(['id', 'event', 'url', 'is_active', 'created_at']),
            'secret' => $secret,
        ], 'Webhook created successfully. Save the signing secret; it will not be shown again.', 201);
    }

    public function update(Request $request, string $id, WebhookUrlValidator $validator): JsonResponse
    {
        $webhook = $this->findWebhook($request, $id);
        $data = $request->validate([
            'event' => ['sometimes', 'required', Rule::in(self::EVENTS)],
            'url' => ['sometimes', 'required', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['url']) && ! $validator->isSafeHttpsUrl($data['url'])) {
            throw new ApiException('VALIDATION_ERROR', 'Webhook URL must resolve to a public HTTPS destination.', 422, ['url' => ['The supplied URL is not an allowed webhook destination.']]);
        }
        $webhook->fill($data)->save();

        return $this->successResponse($webhook->only(['id', 'event', 'url', 'is_active', 'last_triggered_at', 'failure_count']), 'Webhook updated successfully.');
    }

    public function rotateSecret(Request $request, string $id): JsonResponse
    {
        $webhook = $this->findWebhook($request, $id);
        $secret = Str::random(64);
        $webhook->update(['secret' => $secret]);

        return $this->successResponse(['id' => $webhook->id, 'secret' => $secret], 'Webhook secret rotated successfully. Save the new secret now.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findWebhook($request, $id)->delete();

        return $this->noContentResponse('Webhook deleted successfully.');
    }

    private function findWebhook(Request $request, string $id): Webhook
    {
        return Webhook::query()->forBusiness($this->business($request)->id)->findOrFail($id);
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
