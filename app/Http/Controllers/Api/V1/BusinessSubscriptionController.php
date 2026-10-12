<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BusinessSubscriptionController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        $subscription = Subscription::query()->forBusiness($this->business($request)->id)
            ->with('plan')->latest()->first();

        if ($subscription === null) {
            return $this->successResponse(null, 'No subscription is configured for this store.');
        }

        return $this->successResponse([
            ...$subscription->only(['id', 'plan_id', 'status', 'starts_at', 'ends_at', 'trial_ends_at', 'cancelled_at', 'created_at']),
            'plan' => $subscription->plan?->only(['id', 'name', 'slug', 'price', 'billing_cycle', 'features', 'limits']),
        ], 'Subscription retrieved successfully.');
    }

    public function subscribe(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate(['plan_id' => ['required', 'integer', 'exists:plans,id']]);
        $plan = Plan::query()->where('is_active', true)->findOrFail($data['plan_id']);
        $subscription = DB::transaction(function () use ($business, $plan): Subscription {
            $current = Subscription::query()->forBusiness($business->id)->whereIn('status', ['trial', 'active', 'past_due'])->lockForUpdate()->latest()->first();
            if ($current !== null) {
                throw new ApiException('CONFLICT', 'This store already has an active or trial subscription.', 409);
            }
            if ((float) $plan->price > 0 && (int) $plan->trial_days === 0) {
                throw new ApiException('PAYMENT_FAILED', 'A payment provider must be configured before starting a paid plan.', 422);
            }
            $startsAt = now();
            $trialEnd = (int) $plan->trial_days > 0 ? now()->addDays((int) $plan->trial_days) : null;

            return Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => $trialEnd !== null ? 'trial' : 'active',
                'starts_at' => $startsAt,
                'ends_at' => $trialEnd,
                'trial_ends_at' => $trialEnd,
            ])->load('plan');
        });

        return $this->successResponse([
            'id' => $subscription->id,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'plan' => $subscription->plan->only(['id', 'name', 'slug', 'price', 'billing_cycle', 'features', 'limits']),
        ], 'Subscription created successfully.', 201);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate(['plan_id' => ['required', 'integer', 'exists:plans,id']]);
        $plan = Plan::query()->where('is_active', true)->findOrFail($data['plan_id']);
        if ((float) $plan->price > 0) {
            throw new ApiException('PAYMENT_FAILED', 'Plan changes requiring payment are unavailable until a payment provider is configured.', 422);
        }
        $subscription = Subscription::query()->forBusiness($business->id)->whereIn('status', ['trial', 'active'])->latest()->firstOrFail();
        $subscription->update(['plan_id' => $plan->id, 'status' => 'active', 'ends_at' => null, 'trial_ends_at' => null]);

        $updated = $subscription->fresh('plan');

        return $this->successResponse([
            ...$updated->only(['id', 'plan_id', 'status', 'starts_at', 'ends_at']),
            'plan' => $updated->plan?->only(['id', 'name', 'slug', 'price', 'billing_cycle', 'features', 'limits']),
        ], 'Subscription plan updated successfully.');
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $subscription = Subscription::query()->forBusiness($this->business($request)->id)
            ->whereIn('status', ['trial', 'active', 'past_due'])->latest()->firstOrFail();
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'] ?? null,
        ]);

        return $this->successResponse($subscription->only(['id', 'status', 'cancelled_at', 'cancellation_reason']), 'Subscription cancelled successfully.');
    }

    public function invoices(Request $request): JsonResponse
    {
        $paginator = $this->business($request)->hasManyThrough(
            \App\Models\SubscriptionInvoice::class,
            Subscription::class,
            'business_id',
            'subscription_id',
            'id',
            'id',
        )->latest('subscription_invoices.id')->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($paginator, 'Subscription invoices retrieved successfully.', fn ($invoice): array => $invoice->only([
            'id', 'subscription_id', 'invoice_number', 'amount', 'currency', 'status', 'paid_at', 'due_at', 'pdf_url', 'created_at',
        ]));
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
