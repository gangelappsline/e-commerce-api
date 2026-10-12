<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class BusinessCategoryController extends TenantCrudController
{
    protected string $modelClass = Category::class;
    protected array $responseFields = ['id', 'parent_id', 'name', 'slug', 'description', 'image', 'icon', 'sort_order', 'is_active', 'is_featured', 'meta_title', 'meta_description', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        $slugRule = Rule::unique('categories', 'slug')->where('business_id', $business->id);
        if ($id !== null) {
            $slugRule->ignore($id);
        }

        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'slug' => [$id === null ? 'required' : 'sometimes', 'string', 'alpha_dash:ascii', 'max:160', $slugRule],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $business->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return Category::query()->forBusiness($business->id);
    }

    public function tree(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $categories = $this->query($business)
            ->whereNull('parent_id')
            ->with(['children' => fn (Builder $query) => $query->where('business_id', $business->id)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return $this->successResponse($categories->map(fn (Category $category): array => [
            ...$category->only($this->responseFields),
            'children' => $category->children->map(fn (Category $child): array => $child->only($this->responseFields))->all(),
        ]), 'Category tree retrieved successfully.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'category_ids' => ['required', 'array', 'min:1', 'max:500'],
            'category_ids.*' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')->where('business_id', $business->id)],
        ]);

        DB::transaction(function () use ($data, $business): void {
            foreach ($data['category_ids'] as $position => $categoryId) {
                Category::query()->forBusiness($business->id)->whereKey($categoryId)->update(['sort_order' => $position]);
            }
        });

        return $this->noContentResponse('Categories reordered successfully.');
    }
}
