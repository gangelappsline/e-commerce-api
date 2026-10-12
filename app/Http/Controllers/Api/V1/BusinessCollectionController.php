<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class BusinessCollectionController extends TenantCrudController
{
    protected string $modelClass = Collection::class;
    protected array $responseFields = ['id', 'name', 'slug', 'description', 'image', 'type', 'rules', 'is_active', 'is_featured', 'sort_order', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        $slug = Rule::unique('collections', 'slug')->where('business_id', $business->id);
        if ($id !== null) {
            $slug->ignore($id);
        }

        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'slug' => [$id === null ? 'required' : 'sometimes', 'string', 'alpha_dash:ascii', 'max:160', $slug],
            'description' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'type' => ['sometimes', Rule::in(['manual', 'automatic'])],
            'rules' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return Collection::query()->forBusiness($business->id);
    }

    public function attachProducts(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $collection = $this->findRecord($request, $id);
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('business_id', $business->id)],
        ]);

        DB::transaction(function () use ($collection, $data): void {
            foreach ($data['product_ids'] as $position => $productId) {
                $collection->products()->syncWithoutDetaching([$productId => ['sort_order' => $position]]);
            }
        });

        return $this->successResponse($collection->load('products')->products->map(fn (Product $product): array => $product->only(['id', 'name', 'slug', 'sku'])), 'Products added to collection.');
    }

    public function detachProduct(Request $request, string $id, string $productId): JsonResponse
    {
        $collection = $this->findRecord($request, $id);
        $collection->products()->detach($productId);

        return $this->noContentResponse('Product removed from collection.');
    }
}
