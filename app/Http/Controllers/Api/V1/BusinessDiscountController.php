<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\Discount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class BusinessDiscountController extends TenantCrudController
{
    protected string $modelClass = Discount::class;
    protected array $responseFields = ['id', 'name', 'type', 'value', 'conditions', 'priority', 'is_active', 'starts_at', 'expires_at', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'type' => [$id === null ? 'required' : 'sometimes', 'string', 'in:percentage,fixed_amount,free_shipping'],
            'value' => [$id === null ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'conditions' => ['sometimes', 'nullable', 'array'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return Discount::query()->forBusiness($business->id);
    }
}
