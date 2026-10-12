<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BusinessCouponController extends TenantCrudController
{
    protected string $modelClass = Coupon::class;
    protected array $responseFields = ['id', 'code', 'name', 'description', 'type', 'value', 'minimum_order_amount', 'maximum_discount', 'usage_limit', 'usage_limit_per_customer', 'used_count', 'applies_to', 'applies_to_ids', 'customer_eligibility', 'eligible_customer_ids', 'starts_at', 'expires_at', 'is_active', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        $code = Rule::unique('coupons', 'code')->where('business_id', $business->id);
        if ($id !== null) {
            $code->ignore($id);
        }

        return [
            'code' => [$id === null ? 'required' : 'sometimes', 'string', 'alpha_dash:ascii', 'max:80', $code],
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => [$id === null ? 'required' : 'sometimes', Rule::in(['percentage', 'fixed_amount', 'free_shipping', 'buy_x_get_y'])],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'minimum_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'maximum_discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'applies_to' => ['sometimes', Rule::in(['all', 'specific_products', 'specific_categories', 'specific_collections'])],
            'applies_to_ids' => ['sometimes', 'nullable', 'array', 'max:500'],
            'applies_to_ids.*' => ['integer', 'min:1'],
            'customer_eligibility' => ['sometimes', Rule::in(['all', 'new_customers', 'specific_customers'])],
            'eligible_customer_ids' => ['sometimes', 'nullable', 'array', 'max:1000'],
            'eligible_customer_ids.*' => ['integer', Rule::exists('customer_business', 'customer_id')->where('business_id', $business->id)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return Coupon::query()->forBusiness($business->id);
    }
}
