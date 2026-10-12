<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Plan;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;

final class PlanController extends BaseController
{
    public function index(): JsonResponse
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get([
            'id', 'name', 'slug', 'description', 'price', 'billing_cycle', 'features', 'limits', 'trial_days', 'is_featured',
        ]);

        return $this->successResponse($plans, 'Plans retrieved successfully.');
    }

    public function show(string $slug): JsonResponse
    {
        $plan = Plan::query()->where('is_active', true)->where('slug', $slug)->firstOrFail([
            'id', 'name', 'slug', 'description', 'price', 'billing_cycle', 'features', 'limits', 'trial_days', 'is_featured',
        ]);

        return $this->successResponse($plan, 'Plan retrieved successfully.');
    }
}
