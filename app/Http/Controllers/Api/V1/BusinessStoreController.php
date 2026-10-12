<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Models\Order;
use App\Services\StoreAssetService;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class BusinessStoreController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(new BusinessResource($this->business($request)), 'Business store retrieved successfully.');
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'required', 'string', 'alpha_dash:ascii', 'min:3', 'max:100', Rule::unique('businesses', 'slug')->ignore($business->id)],
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)(?!-)[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i', Rule::unique('businesses', 'custom_domain')->ignore($business->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha:ascii'],
            'language' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'timezone'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax_included' => ['sometimes', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'meta_keywords' => ['sometimes', 'nullable', 'array', 'max:50'],
        ]);
        unset($data['status'], $data['owner_id'], $data['is_verified'], $data['verified_at']);
        $business->fill($data)->save();

        return $this->successResponse(new BusinessResource($business->fresh()), 'Business store updated successfully.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'shipping' => ['sometimes', 'array'],
            'shipping.instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'shipping.free_shipping_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payments' => ['sometimes', 'array'],
            'payments.available' => ['sometimes', 'array', 'max:20'],
            'payments.available.*.type' => ['required_with:payments.available', 'string', 'in:credit_card,debit_card,paypal,cash_on_delivery,bank_transfer,wallet,other'],
            'payments.available.*.label' => ['sometimes', 'string', 'max:100'],
            'payments.available.*.enabled' => ['sometimes', 'boolean'],
            'payments.available.*.instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'notifications' => ['sometimes', 'array'],
            'notifications.order_email' => ['sometimes', 'boolean'],
            'notifications.low_stock_email' => ['sometimes', 'boolean'],
            'notifications.daily_report_email' => ['sometimes', 'boolean'],
        ]);
        $settings = is_array($business->settings) ? $business->settings : [];
        foreach (['shipping', 'payments', 'notifications'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = $data[$key];
            }
        }
        $business->forceFill(['settings' => $settings])->save();

        return $this->successResponse([
            'shipping' => $settings['shipping'] ?? [],
            'payments' => $settings['payments'] ?? [],
            'notifications' => $settings['notifications'] ?? [],
        ], 'Store settings updated successfully.');
    }

    public function updateTheme(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'theme' => ['required', 'array'],
            'theme.name' => ['sometimes', 'string', 'max:80'],
            'theme.primary_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.secondary_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.font_family' => ['sometimes', 'string', 'max:100'],
            'theme.logo_position' => ['sometimes', 'in:left,center'],
            'theme.homepage_sections' => ['sometimes', 'array', 'max:30'],
        ]);
        $settings = is_array($business->settings) ? $business->settings : [];
        $settings['theme'] = $data['theme'];
        $business->forceFill(['settings' => $settings])->save();

        return $this->successResponse($settings['theme'], 'Store theme updated successfully.');
    }

    public function uploadLogo(Request $request, StoreAssetService $assets): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        $business = $this->business($request);
        $url = $assets->store($data['file'], $business, 'logo');
        $business->forceFill(['logo' => $url])->save();

        return $this->successResponse(['logo' => $url], 'Store logo uploaded successfully.');
    }

    public function uploadBanner(Request $request, StoreAssetService $assets): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480']]);
        $business = $this->business($request);
        $url = $assets->store($data['file'], $business, 'banner');
        $business->forceFill(['banner' => $url])->save();

        return $this->successResponse(['banner' => $url], 'Store banner uploaded successfully.');
    }

    public function analytics(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $start = now()->subDays(29)->startOfDay();
        $orders = Order::query()->forBusiness($business->id)->where('created_at', '>=', $start);
        $daily = (clone $orders)->where('payment_status', 'paid')
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(*) as orders')
            ->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();

        return $this->successResponse([
            'period' => ['from' => $start->toISOString(), 'to' => now()->toISOString()],
            'orders' => (clone $orders)->count(),
            'paid_revenue' => (clone $orders)->where('payment_status', 'paid')->sum('grand_total'),
            'average_order_value' => round((float) ((clone $orders)->where('payment_status', 'paid')->avg('grand_total') ?? 0), 2),
            'daily_sales' => $daily,
        ], 'Store analytics retrieved successfully.');
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
