<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\CartResource;
use App\Models\Business;
use App\Services\CartService;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class CartController extends BaseController
{
    public function __construct(private readonly CartService $carts)
    {
    }

    #[OA\Get(
        path: '/api/v1/cart',
        tags: ['Cart'],
        summary: 'Get the current customer or guest cart',
        parameters: [new OA\Parameter(name: 'X-Store-Slug', in: 'header', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'X-Cart-Session', in: 'header', schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Cart retrieved', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess'))],
    )]
    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->resolve($request, $this->business($request));

        return $this->successResponse(new CartResource($cart), 'Cart retrieved successfully.');
    }

    #[OA\Post(
        path: '/api/v1/cart/items',
        tags: ['Cart'],
        summary: 'Add a product to the current cart',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['product_id', 'quantity'], properties: [
            new OA\Property(property: 'product_id', type: 'integer', example: 17),
            new OA\Property(property: 'product_variant_id', type: 'integer', nullable: true, example: 25),
            new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 1000, example: 2),
        ])),
        responses: [new OA\Response(response: 201, description: 'Cart updated', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess')), new OA\Response(response: 409, description: 'Out of stock', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))],
    )]
    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);
        $cart = $this->carts->addItem($request, $this->business($request), $data);

        return $this->successResponse(new CartResource($cart), 'Cart item added successfully.', 201);
    }

    public function updateItem(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:1000']]);
        $cart = $this->carts->updateItem($request, $this->business($request), $id, (int) $data['quantity']);

        return $this->successResponse(new CartResource($cart), 'Cart item updated successfully.');
    }

    public function removeItem(Request $request, string $id): JsonResponse
    {
        $cart = $this->carts->removeItem($request, $this->business($request), $id);

        return $this->successResponse(new CartResource($cart), 'Cart item removed successfully.');
    }

    public function clear(Request $request): JsonResponse
    {
        $this->carts->clear($request, $this->business($request));

        return $this->noContentResponse('Cart cleared successfully.');
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:80']]);
        $cart = $this->carts->applyCoupon($request, $this->business($request), $data['code']);

        return $this->successResponse(new CartResource($cart), 'Coupon applied successfully.');
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->carts->removeCoupon($request, $this->business($request));

        return $this->successResponse(new CartResource($cart), 'Coupon removed successfully.');
    }

    public function estimateShipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);
        $business = $this->business($request);
        $cart = $this->carts->resolve($request, $business, false)->load(['items.product']);
        $methods = $this->carts->estimateShipping($business, $cart, $data);

        return $this->successResponse($methods, 'Shipping estimates retrieved successfully.');
    }

    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'uuid']]);
        $cart = $this->carts->mergeGuestCart($request, $this->business($request), $data['session_id']);

        return $this->successResponse(new CartResource($cart), 'Guest cart merged successfully.');
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
