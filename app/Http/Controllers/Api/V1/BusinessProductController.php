<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\ProductResource;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ImageUploadService;
use App\Support\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BusinessProductController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $query = Product::query()->forBusiness($business->id)->with(['business', 'category', 'brand', 'images', 'variants'])->withSum('inventoryItems', 'available_quantity');
        if ($request->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_substr((string) $request->query('q'), 0, 120)).'%';
            $query->where(fn (Builder $builder) => $builder->where('name', 'like', $term)->orWhere('sku', 'like', $term));
        }
        if ($request->filled('status')) {
            $request->validate(['status' => ['required', 'in:draft,active,inactive']]);
            $query->where('is_active', $request->query('status') === 'active');
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        $paginator = $query->latest('id')->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($paginator, 'Products retrieved successfully.', fn (Product $product): array => (new ProductResource($product))->resolve($request));
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $this->validatedProduct($request, $business);
        $product = Product::create([
            ...$data,
            'business_id' => $business->id,
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'sku' => $data['sku'],
            'is_active' => false,
            'published_at' => null,
        ]);
        $product->load(['business', 'category', 'brand', 'images', 'variants']);

        return $this->successResponse(new ProductResource($product), 'Product created successfully.', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $product = $this->productQuery($request)->with(['business', 'category', 'brand', 'images', 'variants'])->findOrFail($id);

        return $this->successResponse(new ProductResource($product), 'Product retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $product = $this->productQuery($request)->findOrFail($id);
        $data = $this->validatedProduct($request, $business, $product);
        $product->fill($data)->save();
        $product->load(['business', 'category', 'brand', 'images', 'variants']);

        return $this->successResponse(new ProductResource($product), 'Product updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->productQuery($request)->findOrFail($id)->delete();

        return $this->noContentResponse('Product deleted successfully.');
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $product = $this->productQuery($request)->findOrFail($id);
        if ($product->category_id !== null && ! $product->category()->where('business_id', $product->business_id)->exists()) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The product category is invalid for this store.', 422);
        }
        $product->forceFill(['is_active' => true, 'published_at' => $product->published_at ?? now()])->save();
        $product->searchable();

        return $this->successResponse(new ProductResource($product->load(['business', 'category', 'brand', 'images', 'variants'])), 'Product published successfully.');
    }

    public function unpublish(Request $request, string $id): JsonResponse
    {
        $product = $this->productQuery($request)->findOrFail($id);
        $product->forceFill(['is_active' => false])->save();
        $product->unsearchable();

        return $this->successResponse(new ProductResource($product), 'Product unpublished successfully.');
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $product = $this->productQuery($request)->with(['images', 'variants'])->findOrFail($id);
        $copy = DB::transaction(function () use ($product): Product {
            $copy = $product->replicate(['slug', 'sku', 'published_at', 'view_count', 'sold_count']);
            $copy->name = $product->name.' (Copy)';
            $copy->slug = Str::slug($copy->name).'-'.Str::lower(Str::random(6));
            $copy->sku = $product->sku.'-COPY-'.Str::upper(Str::random(5));
            $copy->is_active = false;
            $copy->published_at = null;
            $copy->stock = 0;
            $copy->save();
            foreach ($product->images as $image) {
                $copy->images()->create($image->only(['url', 'alt_text', 'sort_order', 'is_primary']));
            }
            foreach ($product->variants as $variant) {
                $copy->variants()->create([
                    'business_id' => $copy->business_id,
                    'name' => $variant->name,
                    'sku' => $variant->sku.'-COPY-'.Str::upper(Str::random(4)),
                    'price' => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'cost_price' => $variant->cost_price,
                    'stock' => 0,
                    'weight' => $variant->weight,
                    'option_values' => $variant->option_values,
                    'is_active' => $variant->is_active,
                    'is_default' => $variant->is_default,
                    'sort_order' => $variant->sort_order,
                ]);
            }

            return $copy->load(['business', 'category', 'brand', 'images', 'variants']);
        });

        return $this->successResponse(new ProductResource($copy), 'Product duplicated successfully.', 201);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('business_id', $business->id)],
            'updates' => ['required', 'array', 'min:1'],
            'updates.is_active' => ['sometimes', 'boolean'],
            'updates.is_featured' => ['sometimes', 'boolean'],
            'updates.category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $business->id)],
        ]);
        $updates = $data['updates'];
        if (array_key_exists('is_active', $updates) && $updates['is_active']) {
            $updates['published_at'] = now();
        }
        $updated = Product::query()->forBusiness($business->id)->whereIn('id', $data['product_ids'])->update($updates);

        return $this->successResponse(['updated_count' => $updated], 'Products updated successfully.');
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $business = $this->business($request);
        $handle = fopen($data['file']->getRealPath(), 'rb');
        if ($handle === false) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The uploaded CSV file could not be read.', 422);
        }
        $headers = fgetcsv($handle);
        if (! is_array($headers) || ! in_array('name', $headers, true) || ! in_array('sku', $headers, true) || ! in_array('price', $headers, true)) {
            fclose($handle);
            throw new ApiException('VALIDATION_ERROR', 'CSV must include name, sku, and price columns.', 422);
        }
        $imported = 0;
        $failures = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false && $line <= 5000) {
            $line++;
            if (count($row) !== count($headers)) {
                $failures[] = ['line' => $line, 'message' => 'The row does not match the header column count.'];
                continue;
            }
            $record = array_combine($headers, $row);
            if (! is_array($record) || trim((string) $record['name']) === '' || trim((string) $record['sku']) === '' || ! is_numeric($record['price']) || (float) $record['price'] < 0) {
                $failures[] = ['line' => $line, 'message' => 'The product name, SKU, or price is invalid.'];
                continue;
            }
            try {
                Product::create([
                    'business_id' => $business->id,
                    'name' => mb_substr(trim((string) $record['name']), 0, 200),
                    'slug' => Str::slug((string) $record['name']).'-'.Str::lower(Str::random(5)),
                    'sku' => mb_substr(trim((string) $record['sku']), 0, 100),
                    'price' => (float) $record['price'],
                    'stock' => max(0, (int) ($record['stock'] ?? 0)),
                    'is_active' => false,
                    'track_inventory' => true,
                ]);
                $imported++;
            } catch (\Throwable) {
                $failures[] = ['line' => $line, 'message' => 'The SKU conflicts with an existing product or the row could not be saved.'];
            }
        }
        fclose($handle);

        return $this->successResponse(['imported' => $imported, 'failed' => count($failures), 'failures' => array_slice($failures, 0, 100)], 'Product import completed.');
    }

    public function export(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $products = Product::query()->forBusiness($business->id)->orderBy('id')->limit(5000)
            ->get(['sku', 'name', 'slug', 'price', 'compare_at_price', 'stock', 'is_active', 'is_featured']);
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['sku', 'name', 'slug', 'price', 'compare_at_price', 'stock', 'is_active', 'is_featured']);
        foreach ($products as $product) {
            fputcsv($stream, $product->only(['sku', 'name', 'slug', 'price', 'compare_at_price', 'stock', 'is_active', 'is_featured']));
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $this->successResponse([
            'filename' => 'products-'.$business->slug.'-'.now()->format('Ymd-His').'.csv',
            'encoding' => 'base64',
            'content' => base64_encode($csv),
            'truncated' => $products->count() === 5000,
        ], 'Product export generated successfully.');
    }

    public function uploadImages(Request $request, string $id, ImageUploadService $images): JsonResponse
    {
        $business = $this->business($request);
        $product = $this->productQuery($request)->findOrFail($id);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:250'],
        ]);
        $created = DB::transaction(function () use ($data, $request, $business, $product, $images): array {
            $created = [];
            foreach ($data['images'] as $position => $file) {
                $url = $images->uploadProductImage($file, $business, $product);
                $image = $product->images()->create([
                    'url' => $url,
                    'alt_text' => $data['alt_text'] ?? $product->name,
                    'sort_order' => (int) $product->images()->max('sort_order') + 1,
                    'is_primary' => $product->images()->count() === 0 && $position === 0,
                ]);
                $created[] = $image->only(['id', 'url', 'alt_text', 'sort_order', 'is_primary']);
            }

            return $created;
        });

        return $this->successResponse($created, 'Product images uploaded successfully.', 201);
    }

    public function deleteImage(Request $request, string $id, string $imageId): JsonResponse
    {
        $product = $this->productQuery($request)->findOrFail($id);
        $image = $product->images()->whereKey($imageId)->firstOrFail();
        $image->delete();
        if ($image->is_primary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return $this->noContentResponse('Product image deleted successfully.');
    }

    public function reorderImages(Request $request, string $id): JsonResponse
    {
        $product = $this->productQuery($request)->findOrFail($id);
        $data = $request->validate([
            'image_ids' => ['required', 'array', 'min:1', 'max:100'],
            'image_ids.*' => ['required', 'integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $product->id)],
        ]);
        DB::transaction(function () use ($data, $product): void {
            foreach ($data['image_ids'] as $position => $imageId) {
                $product->images()->whereKey($imageId)->update(['sort_order' => $position, 'is_primary' => $position === 0]);
            }
        });

        return $this->noContentResponse('Product images reordered successfully.');
    }

    public function variants(Request $request, string $id): JsonResponse
    {
        $product = $this->productQuery($request)->findOrFail($id);

        return $this->successResponse($product->variants()->orderBy('sort_order')->get()->map(fn (ProductVariant $variant): array => $variant->only([
            'id', 'name', 'sku', 'barcode', 'price', 'compare_at_price', 'cost_price', 'stock', 'weight', 'image_id', 'option_values', 'is_active', 'is_default', 'sort_order',
        ])), 'Product variants retrieved successfully.');
    }

    public function createVariant(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $product = $this->productQuery($request)->findOrFail($id);
        $data = $this->variantData($request, $business, null, $product);
        $variant = $product->variants()->create(['business_id' => $business->id, ...$data]);

        return $this->successResponse($variant->only(['id', 'product_id', 'name', 'sku', 'price', 'stock', 'option_values', 'is_active', 'is_default']), 'Product variant created successfully.', 201);
    }

    public function updateVariant(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $variant = ProductVariant::query()->forBusiness($business->id)->findOrFail($id);
        $product = $this->productQuery($request)->findOrFail($variant->product_id);
        $variant->fill($this->variantData($request, $business, $variant, $product))->save();

        return $this->successResponse($variant->fresh()->only(['id', 'product_id', 'name', 'sku', 'price', 'stock', 'option_values', 'is_active', 'is_default']), 'Product variant updated successfully.');
    }

    public function deleteVariant(Request $request, string $id): JsonResponse
    {
        ProductVariant::query()->forBusiness($this->business($request)->id)->findOrFail($id)->delete();

        return $this->noContentResponse('Product variant deleted successfully.');
    }

    private function validatedProduct(Request $request, Business $business, ?Product $product = null): array
    {
        $id = $product?->id;
        $slug = Rule::unique('products', 'slug')->where('business_id', $business->id);
        $sku = Rule::unique('products', 'sku')->where('business_id', $business->id);
        if ($id !== null) {
            $slug->ignore($id);
            $sku->ignore($id);
        }
        $categoryRule = Rule::exists('categories', 'id')->where('business_id', $business->id);
        $brandRule = Rule::exists('brands', 'id')->where('business_id', $business->id);
        $typeRule = Rule::exists('product_types', 'id')->where('business_id', $business->id);

        return $request->validate([
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:200'],
            'slug' => ['sometimes', 'string', 'alpha_dash:ascii', 'max:220', $slug],
            'sku' => [$id === null ? 'required' : 'sometimes', 'string', 'max:100', $sku],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'integer', $categoryRule],
            'brand_id' => ['sometimes', 'nullable', 'integer', $brandRule],
            'product_type_id' => ['sometimes', 'nullable', 'integer', $typeRule],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => [$id === null ? 'required' : 'sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'compare_at_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'tax_included' => ['sometimes', 'nullable', 'boolean'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'dimensions' => ['sometimes', 'nullable', 'array'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'track_inventory' => ['sometimes', 'boolean'],
            'allow_backorder' => ['sometimes', 'boolean'],
            'requires_shipping' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_digital' => ['sometimes', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'meta_keywords' => ['sometimes', 'nullable', 'array'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:50'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    private function variantData(Request $request, Business $business, ?ProductVariant $variant, Product $product): array
    {
        $sku = Rule::unique('product_variants', 'sku')->where('business_id', $business->id);
        if ($variant !== null) {
            $sku->ignore($variant->id);
        }

        return $request->validate([
            'name' => [$variant === null ? 'required' : 'sometimes', 'string', 'max:180'],
            'sku' => [$variant === null ? 'required' : 'sometimes', 'string', 'max:100', $sku],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'image_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_images', 'id')->where('product_id', $product->id)],
            'option_values' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function productQuery(Request $request): Builder
    {
        return Product::query()->forBusiness($this->business($request)->id);
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
