<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BusinessShippingMethodController extends TenantCrudController
{
    protected string $modelClass = ShippingMethod::class;
    protected array $responseFields = ['id', 'zone_id', 'name', 'type', 'price', 'min_order_amount', 'max_order_amount', 'weight_from', 'weight_to', 'carrier', 'estimated_days_min', 'estimated_days_max', 'is_active', 'sort_order', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        return [
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('shipping_zones', 'id')->where('business_id', $business->id)],
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'type' => [$id === null ? 'required' : 'sometimes', Rule::in(['flat_rate', 'free', 'weight_based', 'price_based', 'carrier'])],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'min_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['sometimes', 'nullable', 'numeric', 'gte:min_order_amount'],
            'weight_from' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weight_to' => ['sometimes', 'nullable', 'numeric', 'gte:weight_from'],
            'carrier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'estimated_days_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'estimated_days_max' => ['sometimes', 'nullable', 'integer', 'gte:estimated_days_min', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return ShippingMethod::query()->forBusiness($business->id);
    }
}
