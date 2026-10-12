<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\Review;
use App\Support\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Throwable;

final class CatalogController extends BaseController
{
    #[OA\Get(
        path: '/api/v1/products',
        tags: ['Products'],
        summary: 'List tenant products with filters and pagination',
        parameters: [
            new OA\Parameter(name: 'X-Store-Slug', in: 'header', required: true, schema: new OA\Schema(type: 'string', example: 'northwind-goods')),
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string', example: 'backpack')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'brand', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'min_price', in: 'query', schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'max_price', in: 'query', schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['newest', 'price_asc', 'price_desc', 'top_rated', 'best_selling']))
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated product listing', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess'))],
    )]
    public function products(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $query = $this->publishedProducts($business);
        $searchTerm = trim((string) $request->query('q', ''));

        if ($searchTerm !== '') {
            $searchTerm = mb_substr($searchTerm, 0, 120);
            $ids = $this->scoutKeys($searchTerm, $business);
            if ($ids !== []) {
                $query->whereIn('products.id', $ids);
            } else {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $searchTerm).'%';
                $query->where(function (Builder $builder) use ($like): void {
                    $builder->where('products.name', 'like', $like)
                        ->orWhere('products.sku', 'like', $like)
                        ->orWhere('products.short_description', 'like', $like)
                        ->orWhere('products.description', 'like', $like);
                });
            }
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn (Builder $builder) => $builder->where('slug', $request->string('category')->toString()));
        }
        if ($request->filled('brand')) {
            $query->whereHas('brand', fn (Builder $builder) => $builder->where('slug', $request->string('brand')->toString()));
        }
        if ($request->filled('min_price')) {
            $query->where('products.price', '>=', max(0, (float) $request->query('min_price')));
        }
        if ($request->filled('max_price')) {
            $query->where('products.price', '<=', max(0, (float) $request->query('max_price')));
        }
        if ($request->boolean('featured')) {
            $query->where('products.is_featured', true);
        }
        if ($request->boolean('in_stock')) {
            $query->where(function (Builder $builder): void {
                $builder->where('products.stock', '>', 0)
                    ->orWhereExists(function ($subquery): void {
                        $subquery->selectRaw('1')->from('inventory_items')
                            ->whereColumn('inventory_items.product_id', 'products.id')
                            ->whereColumn('inventory_items.business_id', 'products.business_id')
                            ->where('inventory_items.available_quantity', '>', 0);
                    });
            });
        }

        match ($request->query('sort', 'newest')) {
            'price_asc' => $query->orderBy('products.price'),
            'price_desc' => $query->orderByDesc('products.price'),
            'top_rated' => $query->orderByDesc('products.rating_average')->orderByDesc('products.rating_count'),
            'best_selling' => $query->orderByDesc('products.sold_count'),
            default => $query->orderByDesc('products.published_at'),
        };

        $perPage = min(max((int) $request->integer('per_page', 15), 1), (int) config('shopline.max_page_size', 100));
        $paginator = $query->paginate($perPage);

        return $this->paginatedResponse($paginator, 'Products retrieved successfully.', fn (Product $product): array => (new ProductResource($product))->resolve($request));
    }

    #[OA\Get(
        path: '/api/v1/products/{slug}',
        tags: ['Products'],
        summary: 'Retrieve a published product by slug',
        parameters: [new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Product retrieved', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess')), new OA\Response(response: 404, description: 'Product not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))],
    )]
    public function product(Request $request, string $slug): JsonResponse
    {
        $product = $this->publishedProducts($this->business($request))
            ->with(['business', 'category', 'brand', 'images', 'variants'])
            ->where('slug', $slug)
            ->firstOrFail();
        Product::query()->forBusiness((int) $product->business_id)->whereKey($product->id)->increment('view_count');
        $product->setAttribute('view_count', (int) $product->view_count + 1);

        return $this->successResponse(new ProductResource($product), 'Product retrieved successfully.');
    }

    public function relatedProducts(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $product = $this->publishedProducts($business)->findOrFail($id);
        $query = $this->publishedProducts($business)->where('products.id', '!=', $product->id);
        if ($product->category_id !== null) {
            $query->where('category_id', $product->category_id);
        } elseif ($product->brand_id !== null) {
            $query->where('brand_id', $product->brand_id);
        }
        $products = $query->with(['business', 'category', 'brand', 'images'])->limit(12)->get();

        return $this->successResponse(ProductResource::collection($products), 'Related products retrieved successfully.');
    }

    public function productReviews(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $product = $this->publishedProducts($business)->findOrFail($id);
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginator = Review::query()->forBusiness($business->id)
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->with('customer.user')
            ->latest()
            ->paginate($perPage);

        return $this->paginatedResponse($paginator, 'Product reviews retrieved successfully.', fn (Review $review): array => (new ReviewResource($review))->resolve($request));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1', 'max:120']]);

        return $this->products($request);
    }

    public function categories(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $categories = Category::query()->forBusiness($business->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name', 'slug', 'description', 'image', 'icon', 'sort_order']);

        return $this->successResponse($categories, 'Categories retrieved successfully.');
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        $category = Category::query()->forBusiness($this->business($request)->id)->where('is_active', true)
            ->where('slug', $slug)->with(['parent:id,name,slug'])->firstOrFail();

        return $this->successResponse([
            ...$category->only(['id', 'parent_id', 'name', 'slug', 'description', 'image', 'icon', 'sort_order']),
            'parent' => $category->parent?->only(['id', 'name', 'slug']),
        ], 'Category retrieved successfully.');
    }

    public function categoryProducts(Request $request, string $slug): JsonResponse
    {
        $business = $this->business($request);
        $category = Category::query()->forBusiness($business->id)->where('is_active', true)->where('slug', $slug)->firstOrFail();
        $query = $this->publishedProducts($business)->where('category_id', $category->id);
        $paginator = $query->with(['business', 'category', 'brand', 'images'])->orderByDesc('published_at')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($paginator, 'Category products retrieved successfully.', fn (Product $product): array => (new ProductResource($product))->resolve($request));
    }

    public function brands(Request $request): JsonResponse
    {
        $brands = Brand::query()->forBusiness($this->business($request)->id)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'slug', 'description', 'logo', 'website', 'is_featured']);

        return $this->successResponse($brands, 'Brands retrieved successfully.');
    }

    public function brand(Request $request, string $slug): JsonResponse
    {
        $brand = Brand::query()->forBusiness($this->business($request)->id)->where('is_active', true)->where('slug', $slug)->firstOrFail();

        return $this->successResponse($brand->only(['id', 'name', 'slug', 'description', 'logo', 'website', 'is_featured']), 'Brand retrieved successfully.');
    }

    public function collections(Request $request): JsonResponse
    {
        $collections = Collection::query()->forBusiness($this->business($request)->id)->where('is_active', true)
            ->orderBy('sort_order')->get(['id', 'name', 'slug', 'description', 'image', 'type', 'is_featured', 'sort_order']);

        return $this->successResponse($collections, 'Collections retrieved successfully.');
    }

    public function collection(Request $request, string $slug): JsonResponse
    {
        $collection = Collection::query()->forBusiness($this->business($request)->id)->where('is_active', true)->where('slug', $slug)->firstOrFail();

        return $this->successResponse($collection->only(['id', 'name', 'slug', 'description', 'image', 'type', 'is_featured', 'sort_order']), 'Collection retrieved successfully.');
    }

    public function collectionProducts(Request $request, string $slug): JsonResponse
    {
        $business = $this->business($request);
        $collection = Collection::query()->forBusiness($business->id)->where('is_active', true)->where('slug', $slug)->firstOrFail();
        $paginator = $collection->products()->where('products.business_id', $business->id)->where('products.is_active', true)
            ->whereNotNull('products.published_at')->where('products.published_at', '<=', now())
            ->with(['business', 'category', 'brand', 'images'])->orderBy('collection_product.sort_order')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($paginator, 'Collection products retrieved successfully.', fn (Product $product): array => (new ProductResource($product))->resolve($request));
    }

    public function tags(Request $request): JsonResponse
    {
        $tags = ProductTag::query()->forBusiness($this->business($request)->id)->orderBy('name')->get(['id', 'name', 'slug']);

        return $this->successResponse($tags, 'Product tags retrieved successfully.');
    }

    public function filters(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $base = $this->publishedProducts($business);
        $facets = [
            'categories' => Category::query()->forBusiness($business->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'brands' => Brand::query()->forBusiness($business->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'price' => [
                'min' => (float) ((clone $base)->min('price') ?? 0),
                'max' => (float) ((clone $base)->max('price') ?? 0),
            ],
            'attributes' => DB::table('attributes')
                ->join('product_attribute_values', 'product_attribute_values.attribute_id', '=', 'attributes.id')
                ->join('products', 'products.id', '=', 'product_attribute_values.product_id')
                ->where('attributes.business_id', $business->id)
                ->where('attributes.is_filterable', true)
                ->where('products.business_id', $business->id)
                ->where('products.is_active', true)
                ->distinct()->orderBy('attributes.sort_order')
                ->get(['attributes.id', 'attributes.name', 'attributes.slug', 'attributes.type']),
        ];

        return $this->successResponse($facets, 'Product filters retrieved successfully.');
    }

    private function publishedProducts(Business $business): Builder
    {
        return Product::query()->forBusiness($business->id)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['business', 'category', 'brand', 'images'])
            ->withSum('inventoryItems', 'available_quantity');
    }

    /** @return list<int> */
    private function scoutKeys(string $query, Business $business): array
    {
        try {
            return Product::search($query)->where('business_id', (int) $business->id)->keys()->map(fn ($id): int => (int) $id)->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
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
