<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class BusinessInventoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $query = InventoryItem::query()->forBusiness($business->id)->with(['product:id,name,sku,low_stock_threshold', 'variant:id,name,sku', 'location:id,name']);
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }
        $paginator = $query->latest('id')->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));

        return $this->paginatedResponse($paginator, 'Inventory items retrieved successfully.', fn (InventoryItem $item): array => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'location_id' => $item->location_id,
            'quantity' => $item->quantity,
            'reserved_quantity' => $item->reserved_quantity,
            'available_quantity' => $item->available_quantity,
            'product' => $item->product?->only(['id', 'name', 'sku', 'low_stock_threshold']),
            'variant' => $item->variant?->only(['id', 'name', 'sku']),
            'location' => $item->location?->only(['id', 'name']),
        ]);
    }

    public function locations(Request $request): JsonResponse
    {
        $locations = InventoryLocation::query()->forBusiness($this->business($request)->id)->orderByDesc('is_default')->orderBy('name')->get();

        return $this->successResponse($locations->map(fn (InventoryLocation $location): array => $location->only(['id', 'name', 'address', 'phone', 'is_default', 'is_active', 'created_at', 'updated_at'])), 'Inventory locations retrieved successfully.');
    }

    public function createLocation(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('inventory_locations', 'name')->where('business_id', $business->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $location = DB::transaction(function () use ($business, $data): InventoryLocation {
            if (($data['is_default'] ?? false) === true) {
                InventoryLocation::query()->forBusiness($business->id)->update(['is_default' => false]);
            }

            return InventoryLocation::create(['business_id' => $business->id, ...$data]);
        });

        return $this->successResponse($location->only(['id', 'name', 'address', 'phone', 'is_default', 'is_active']), 'Inventory location created successfully.', 201);
    }

    public function updateLocation(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $location = InventoryLocation::query()->forBusiness($business->id)->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('inventory_locations', 'name')->where('business_id', $business->id)->ignore($location->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($business, $location, $data): void {
            if (($data['is_default'] ?? false) === true) {
                InventoryLocation::query()->forBusiness($business->id)->where('id', '!=', $location->id)->update(['is_default' => false]);
            }
            $location->fill($data)->save();
        });

        return $this->successResponse($location->fresh()->only(['id', 'name', 'address', 'phone', 'is_default', 'is_active']), 'Inventory location updated successfully.');
    }

    public function deleteLocation(Request $request, string $id): JsonResponse
    {
        $location = InventoryLocation::query()->forBusiness($this->business($request)->id)->findOrFail($id);
        if ($location->items()->exists()) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'A location with inventory cannot be deleted.', 409);
        }
        $location->delete();

        return $this->noContentResponse('Inventory location deleted successfully.');
    }

    public function adjust(Request $request, InventoryService $inventory): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('business_id', $business->id)],
            'product_variant_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_variants', 'id')->where('business_id', $business->id)],
            'location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('business_id', $business->id)],
            'quantity_change' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $product = Product::query()->forBusiness($business->id)->findOrFail($data['product_id']);
        if (! empty($data['product_variant_id'])) {
            ProductVariant::query()->forBusiness($business->id)->where('product_id', $product->id)->findOrFail($data['product_variant_id']);
        }
        $variantId = $data['product_variant_id'] ?? null;
        $itemQuery = InventoryItem::query()->forBusiness($business->id)
            ->where('product_id', $product->id)
            ->where('location_id', $data['location_id']);
        $variantId === null ? $itemQuery->whereNull('product_variant_id') : $itemQuery->where('product_variant_id', $variantId);
        $item = $itemQuery->first();
        if ($item === null) {
            if ((int) $data['quantity_change'] < 0) {
                throw new ApiException('BUSINESS_LOGIC_ERROR', 'New inventory records cannot start with a negative quantity.', 422);
            }
            $item = InventoryItem::create([
                'business_id' => $business->id,
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'location_id' => $data['location_id'],
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]);
        }
        $movement = $inventory->adjust($item, (int) $data['quantity_change'], $request->user('api'), $data['notes']);
        $item->refresh();

        return $this->successResponse([
            'inventory_item' => $item->only(['id', 'product_id', 'product_variant_id', 'location_id', 'quantity', 'reserved_quantity', 'available_quantity']),
            'movement' => $movement->only(['id', 'type', 'quantity', 'previous_quantity', 'new_quantity', 'notes', 'created_at']),
        ], 'Inventory adjusted successfully.');
    }

    public function movements(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $query = InventoryMovement::query()->forBusiness($business->id);
        if ($request->filled('product_id')) {
            $query->whereHas('inventoryItem', fn (Builder $builder) => $builder->where('product_id', $request->integer('product_id')));
        }
        $paginator = $query->latest('id')->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));

        return $this->paginatedResponse($paginator, 'Inventory movements retrieved successfully.', fn (InventoryMovement $movement): array => $movement->only([
            'id', 'inventory_item_id', 'user_id', 'type', 'quantity', 'previous_quantity', 'new_quantity', 'reference_type', 'reference_id', 'notes', 'created_at',
        ]));
    }

    public function lowStock(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $products = Product::query()->forBusiness($business->id)->where('track_inventory', true)
            ->withSum('inventoryItems', 'available_quantity')
            ->get(['id', 'name', 'sku', 'stock', 'low_stock_threshold'])
            ->filter(fn (Product $product): bool => $product->available_stock <= (int) $product->low_stock_threshold)
            ->values()->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'available_quantity' => $product->available_stock,
                'low_stock_threshold' => $product->low_stock_threshold,
            ]);

        return $this->successResponse($products, 'Low-stock products retrieved successfully.');
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
