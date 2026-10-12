<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\ReviewResource;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\User;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ReviewController extends BaseController
{
    public function customerReviews(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $paginator = Review::query()->forBusiness($this->business($request)->id)->where('customer_id', $customer->id)
            ->with('product:id,name,slug')->latest()->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($paginator, 'Customer reviews retrieved successfully.', fn (Review $review): array => [
            ...(new ReviewResource($review))->resolve($request),
            'is_approved' => $review->is_approved,
            'product' => $review->product?->only(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $customer = $this->customer($request);
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('business_id', $business->id)->where('is_active', true)],
            'order_id' => ['sometimes', 'nullable', 'integer', Rule::exists('orders', 'id')->where('business_id', $business->id)->where('customer_id', $customer->id)],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'images' => ['sometimes', 'nullable', 'array', 'max:5'],
            'images.*' => ['url', 'max:2048'],
        ]);
        $product = Product::query()->forBusiness($business->id)->where('is_active', true)->findOrFail($data['product_id']);
        $existing = Review::query()->forBusiness($business->id)->where('customer_id', $customer->id)->where('product_id', $product->id)->exists();
        if ($existing) {
            throw new ApiException('CONFLICT', 'A review for this product already exists for this customer.', 409);
        }
        $order = isset($data['order_id']) ? Order::query()->forBusiness($business->id)->where('customer_id', $customer->id)->findOrFail($data['order_id']) : null;
        $verified = $order !== null && $order->items()->where('product_id', $product->id)->exists()
            && in_array($order->payment_status, ['paid', 'partially_paid'], true);
        $review = Review::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'order_id' => $order?->id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'images' => $data['images'] ?? [],
            'is_verified_purchase' => $verified,
            'is_approved' => false,
        ]);

        return $this->successResponse(new ReviewResource($review), 'Review submitted and is awaiting approval.', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $review = $this->customerReview($request, $id);
        $data = $request->validate([
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'images' => ['sometimes', 'nullable', 'array', 'max:5'],
            'images.*' => ['url', 'max:2048'],
        ]);
        $review->fill($data)->forceFill(['is_approved' => false])->save();
        $this->refreshProductRating((int) $review->product_id);

        return $this->successResponse(new ReviewResource($review), 'Review updated successfully and is awaiting approval.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $review = $this->customerReview($request, $id);
        $productId = (int) $review->product_id;
        $review->delete();
        $this->refreshProductRating($productId);

        return $this->noContentResponse('Review deleted successfully.');
    }

    public function vote(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['is_helpful' => ['required', 'boolean']]);
        $review = Review::query()->forBusiness($this->business($request)->id)->where('is_approved', true)->findOrFail($id);
        $user = $request->user('api');

        DB::transaction(function () use ($review, $user, $data): void {
            $locked = Review::query()->lockForUpdate()->findOrFail($review->id);
            $vote = ReviewVote::query()->where('review_id', $locked->id)->where('user_id', $user->id)->lockForUpdate()->first();
            $oldHelpful = $vote?->is_helpful ?? false;
            if ($vote === null) {
                ReviewVote::create(['review_id' => $locked->id, 'user_id' => $user->id, 'is_helpful' => $data['is_helpful']]);
            } else {
                $vote->update(['is_helpful' => $data['is_helpful']]);
            }
            $count = (int) $locked->helpful_count + ((bool) $data['is_helpful'] ? 1 : 0) - ((bool) $oldHelpful ? 1 : 0);
            $locked->update(['helpful_count' => max(0, $count)]);
        });

        return $this->successResponse(['review_id' => $review->id, 'is_helpful' => $data['is_helpful']], 'Review vote saved successfully.');
    }

    private function customerReview(Request $request, string $id): Review
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'Review not found.', 404);
        }

        return Review::query()->forBusiness($this->business($request)->id)
            ->where('customer_id', $this->customer($request)->id)->findOrFail((int) $id);
    }

    private function refreshProductRating(int $productId): void
    {
        $aggregates = Review::query()->where('product_id', $productId)->where('is_approved', true)
            ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as rating_average')->first();
        Product::query()->whereKey($productId)->update([
            'rating_count' => (int) $aggregates->review_count,
            'rating_average' => round((float) $aggregates->rating_average, 2),
        ]);
    }

    private function customer(Request $request): Customer
    {
        $user = $request->user('api');
        if (! $user instanceof User) {
            throw new ApiException('UNAUTHENTICATED', 'Authentication is required.', 401);
        }

        return $user->customer()->firstOrCreate(['user_id' => $user->id]);
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
