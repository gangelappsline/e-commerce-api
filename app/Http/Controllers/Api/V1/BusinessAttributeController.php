<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BusinessAttributeController extends TenantCrudController
{
    protected string $modelClass = Attribute::class;
    protected array $responseFields = ['id', 'name', 'slug', 'type', 'is_filterable', 'is_required', 'sort_order', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        $slug = Rule::unique('attributes', 'slug')->where('business_id', $business->id);
        if ($id !== null) {
            $slug->ignore($id);
        }

        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:120'],
            'slug' => [$id === null ? 'required' : 'sometimes', 'string', 'alpha_dash:ascii', 'max:140', $slug],
            'type' => [$id === null ? 'required' : 'sometimes', Rule::in(['text', 'select', 'color', 'size', 'number', 'boolean'])],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return Attribute::query()->forBusiness($business->id);
    }
}
