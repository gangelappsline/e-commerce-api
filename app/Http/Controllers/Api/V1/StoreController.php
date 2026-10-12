<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\BusinessResource;
use App\Models\Banner;
use App\Models\Business;
use App\Models\Page;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class StoreController extends BaseController
{
    #[OA\Get(
        path: '/api/v1/store',
        tags: ['Storefront'],
        summary: 'Retrieve the resolved store profile',
        parameters: [new OA\Parameter(name: 'X-Store-Slug', in: 'header', required: true, schema: new OA\Schema(type: 'string', example: 'northwind-goods'))],
        responses: [new OA\Response(response: 200, description: 'Store information', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess'))],
    )]
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(new BusinessResource($this->business($request)), 'Store retrieved successfully.');
    }

    public function pages(Request $request): JsonResponse
    {
        $pages = Page::query()->forBusiness($this->business($request)->id)
            ->where('is_published', true)->orderBy('title')
            ->get(['id', 'title', 'slug', 'type', 'meta_title', 'meta_description']);

        return $this->successResponse($pages, 'Store pages retrieved successfully.');
    }

    public function page(Request $request, string $slug): JsonResponse
    {
        $page = Page::query()->forBusiness($this->business($request)->id)
            ->where('is_published', true)->where('slug', $slug)
            ->firstOrFail(['id', 'title', 'slug', 'content', 'type', 'meta_title', 'meta_description', 'updated_at']);

        return $this->successResponse($page, 'Store page retrieved successfully.');
    }

    public function banners(Request $request): JsonResponse
    {
        $query = Banner::query()->forBusiness($this->business($request)->id)
            ->where('is_active', true)
            ->where(fn ($builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
        if ($request->filled('position')) {
            $request->validate(['position' => ['required', 'in:hero,sidebar,footer,popup']]);
            $query->where('position', $request->string('position')->toString());
        }
        $banners = $query->orderBy('sort_order')->get(['id', 'title', 'image', 'link', 'position', 'sort_order', 'starts_at', 'ends_at']);

        return $this->successResponse($banners, 'Store banners retrieved successfully.');
    }

    public function shippingMethods(Request $request): JsonResponse
    {
        $methods = ShippingMethod::query()->forBusiness($this->business($request)->id)
            ->where('is_active', true)->orderBy('sort_order')
            ->get(['id', 'zone_id', 'name', 'type', 'price', 'min_order_amount', 'max_order_amount', 'carrier', 'estimated_days_min', 'estimated_days_max']);

        return $this->successResponse($methods, 'Shipping methods retrieved successfully.');
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $payments = is_array($business->settings) ? ($business->settings['payments'] ?? []) : [];
        $available = is_array($payments) ? ($payments['available'] ?? []) : [];
        $methods = collect($available)->filter(fn ($method): bool => is_array($method) && ($method['enabled'] ?? true))
            ->map(fn (array $method): array => [
                'type' => (string) ($method['type'] ?? 'other'),
                'label' => (string) ($method['label'] ?? $method['type'] ?? 'Other'),
                'instructions' => $method['instructions'] ?? null,
            ])->values();

        return $this->successResponse($methods, 'Payment methods retrieved successfully.');
    }

    public function publicSettings(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $databaseSettings = Setting::query()->where('business_id', $business->id)->where('is_public', true)
            ->orderBy('group')->orderBy('key')->get(['key', 'value', 'group']);
        $safeSettings = [];
        foreach ($databaseSettings as $setting) {
            $safeSettings[$setting->group][$setting->key] = $setting->value;
        }

        return $this->successResponse([
            'store' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'currency' => $business->currency,
                'language' => $business->language,
                'timezone' => $business->timezone,
                'tax_included' => $business->tax_included,
                'tax_rate' => $business->tax_rate,
            ],
            'theme' => $business->settings['theme'] ?? [],
            'public' => $safeSettings,
        ], 'Public store settings retrieved successfully.');
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
