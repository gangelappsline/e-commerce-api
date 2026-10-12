<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Brand;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BusinessBrandController extends TenantCrudController
{
    protected string $modelClass = Brand::class;
    protected array $responseFields = ['id', 'name', 'slug', 'description', 'logo', 'website', 'is_active', 'is_featured', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        $slug = Rule::unique('brands', 'slug')->where('business_id', $business->id);
        if ($id !== null) {
            $slug->ignore($id);
        }

        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'slug' => [$id === null ? 'required' : 'sometimes', 'string', 'alpha_dash:ascii', 'max:160', $slug],
            'description' => ['sometimes', 'nullable', 'string'],
            'logo' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'website' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return Brand::query()->forBusiness($business->id);
    }
}
