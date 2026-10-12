<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Business;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class BusinessAttributeValueController extends BaseController
{
    public function index(Request $request, string $id): JsonResponse
    {
        $attribute = $this->attribute($request, $id);
        $values = $attribute->values()->orderBy('sort_order')->get();

        return $this->successResponse($values->map(fn (AttributeValue $value): array => $value->only(['id', 'attribute_id', 'value', 'slug', 'color_code', 'sort_order'])), 'Attribute values retrieved successfully.');
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $attribute = $this->attribute($request, $id);
        $data = $request->validate([
            'value' => ['required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'alpha_dash:ascii', 'max:160', Rule::unique('attribute_values', 'slug')->where('attribute_id', $attribute->id)],
            'color_code' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $value = $attribute->values()->create([
            'value' => $data['value'],
            'slug' => $data['slug'] ?? Str::slug($data['value']),
            'color_code' => $data['color_code'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $this->successResponse($value->only(['id', 'attribute_id', 'value', 'slug', 'color_code', 'sort_order']), 'Attribute value created successfully.', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $value = $this->value($request, $id);
        $data = $request->validate([
            'value' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'required', 'string', 'alpha_dash:ascii', 'max:160', Rule::unique('attribute_values', 'slug')->where('attribute_id', $value->attribute_id)->ignore($value->id)],
            'color_code' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $value->fill($data)->save();

        return $this->successResponse($value->only(['id', 'attribute_id', 'value', 'slug', 'color_code', 'sort_order']), 'Attribute value updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->value($request, $id)->delete();

        return $this->noContentResponse('Attribute value deleted successfully.');
    }

    private function attribute(Request $request, string $id): Attribute
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'Attribute not found.', 404);
        }

        return Attribute::query()->forBusiness($this->business($request)->id)->findOrFail((int) $id);
    }

    private function value(Request $request, string $id): AttributeValue
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'Attribute value not found.', 404);
        }

        return AttributeValue::query()->whereHas('attribute', fn ($query) => $query->where('business_id', $this->business($request)->id))->findOrFail((int) $id);
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
