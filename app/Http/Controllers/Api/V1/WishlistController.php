<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerBusiness;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class WishlistController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $wishlists = Wishlist::query()->forBusiness($this->business($request)->id)->where('customer_id', $customer->id)
            ->withCount('items')->latest()->get();

        return $this->successResponse($wishlists->map(fn (Wishlist $wishlist): array => $wishlist->only(['id', 'name', 'is_public', 'items_count', 'created_at', 'updated_at'])), 'Wishlists retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $wishlist = Wishlist::create([
            'customer_id' => $this->customer($request)->id,
            'business_id' => $this->business($request)->id,
            ...$data,
        ]);

        return $this->successResponse($wishlist->only(['id', 'name', 'is_public', 'created_at']), 'Wishlist created successfully.', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $wishlist = $this->wishlist($request, $id)->load(['items.product.images', 'items.variant']);

        return $this->successResponse([
            ...$wishlist->only(['id', 'name', 'is_public', 'created_at', 'updated_at']),
            'items' => $wishlist->items->map(fn (WishlistItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'added_at' => $item->added_at,
                'product' => $item->product?->only(['id', 'name', 'slug', 'sku', 'price', 'compare_at_price']),
                'variant' => $item->variant?->only(['id', 'name', 'sku', 'price', 'option_values']),
                'images' => $item->product?->images->map(fn ($image): array => $image->only(['id', 'url', 'alt_text', 'is_primary']))->all() ?? [],
            ])->all(),
        ], 'Wishlist retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $wishlist = $this->wishlist($request, $id);
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
        $wishlist->fill($data)->save();

        return $this->successResponse($wishlist->only(['id', 'name', 'is_public', 'updated_at']), 'Wishlist updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->wishlist($request, $id)->delete();

        return $this->noContentResponse('Wishlist deleted successfully.');
    }

    public function addItem(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $wishlist = $this->wishlist($request, $id);
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('business_id', $business->id)->where('is_active', true)],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_variants', 'id')->where('business_id', $business->id)],
        ]);
        $product = Product::query()->forBusiness($business->id)->where('is_active', true)->findOrFail($data['product_id']);
        $variantId = $data['product_variant_id'] ?? null;
        if ($variantId !== null) {
            ProductVariant::query()->forBusiness($business->id)->where('product_id', $product->id)->where('is_active', true)->findOrFail($variantId);
        }
        $duplicate = WishlistItem::query()->where('wishlist_id', $wishlist->id)->where('product_id', $product->id)
            ->when($variantId === null, fn ($query) => $query->whereNull('product_variant_id'), fn ($query) => $query->where('product_variant_id', $variantId))
            ->exists();
        if ($duplicate) {
            throw new ApiException('CONFLICT', 'This product is already in the wishlist.', 409);
        }
        $item = WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'added_at' => now(),
        ]);

        return $this->successResponse($item->only(['id', 'wishlist_id', 'product_id', 'product_variant_id', 'added_at']), 'Product added to wishlist successfully.', 201);
    }

    public function removeItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $wishlist = $this->wishlist($request, $id);
        $wishlist->items()->whereKey($itemId)->delete();

        return $this->noContentResponse('Wishlist item removed successfully.');
    }

    private function wishlist(Request $request, string $id): Wishlist
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'Wishlist not found.', 404);
        }

        return Wishlist::query()->forBusiness($this->business($request)->id)
            ->where('customer_id', $this->customer($request)->id)->findOrFail((int) $id);
    }

    private function customer(Request $request): Customer
    {
        $user = $request->user('api');
        if (! $user instanceof User) {
            throw new ApiException('UNAUTHENTICATED', 'Authentication is required.', 401);
        }
        $business = $this->business($request);
        $customer = $user->customer()->firstOrCreate(['user_id' => $user->id]);
        $membership = CustomerBusiness::query()->firstOrCreate(['customer_id' => $customer->id, 'business_id' => $business->id]);
        if ($membership->is_blocked) {
            throw new ApiException('UNAUTHORIZED', 'This customer is blocked from this store.', 403);
        }

        return $customer;
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
