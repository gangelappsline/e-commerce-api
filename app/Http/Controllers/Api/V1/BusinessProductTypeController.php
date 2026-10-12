<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BusinessProductTypeController extends TenantCrudController
{
    protected string $modelClass = ProductType::class;
    protected array $responseFields = ['id', 'name', 'slug', 'has_variants', 'attributes', 'is_active', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        $slug = Rule::unique('product_types', 'slug')->where('business_id', $business->id);
        if ($id !== null) {
            $slug->ignore($id);
        }

        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:120'],
            'slug' => [$id === null ? 'required' : 'sometimes', 'string', 'alpha_dash:ascii', 'max:140', $slug],
            'has_variants' => ['sometimes', 'boolean'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return ProductType::query()->forBusiness($business->id);
    }
}
