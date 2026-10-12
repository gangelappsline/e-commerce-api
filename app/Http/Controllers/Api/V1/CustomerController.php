<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\OrderResource;
use App\Models\Business;
use App\Models\Customer;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CustomerController extends BaseController
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $customer = $this->customer($user);

        return $this->successResponse([
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'phone' => $user->phone, 'avatar' => $user->avatar, 'email_verified_at' => $user->email_verified_at,
            ],
            'customer' => $customer->only(['id', 'default_business_id', 'total_orders', 'total_spent', 'loyalty_points', 'accepts_marketing', 'tags', 'created_at']),
        ], 'Customer profile retrieved successfully.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'avatar' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'accepts_marketing' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:30'],
        ]);
        $user->fill(array_intersect_key($data, array_flip(['name', 'phone', 'avatar'])))->save();
        $customer = $this->customer($user);
        $customer->fill(array_intersect_key($data, array_flip(['accepts_marketing', 'tags'])))->save();

        return $this->profile($request);
    }

    public function addresses(Request $request): JsonResponse
    {
        $addresses = $request->user('api')->addresses()->orderByDesc('is_default_shipping')->orderByDesc('is_default_billing')->latest()->get();

        return $this->successResponse($addresses->map(fn (UserAddress $address): array => $this->addressData($address)), 'Customer addresses retrieved successfully.');
    }

    public function createAddress(Request $request): JsonResponse
    {
        $data = $this->addressDataFromRequest($request);
        $user = $request->user('api');
        $address = DB::transaction(function () use ($data, $user): UserAddress {
            if ($data['is_default_shipping'] ?? false) {
                $user->addresses()->update(['is_default_shipping' => false]);
            }
            if ($data['is_default_billing'] ?? false) {
                $user->addresses()->update(['is_default_billing' => false]);
            }

            return $user->addresses()->create($data);
        });

        return $this->successResponse($this->addressData($address), 'Customer address created successfully.', 201);
    }

    public function showAddress(Request $request, string $id): JsonResponse
    {
        $address = $this->address($request, $id);

        return $this->successResponse($this->addressData($address), 'Customer address retrieved successfully.');
    }

    public function updateAddress(Request $request, string $id): JsonResponse
    {
        $address = $this->address($request, $id);
        $data = $this->addressDataFromRequest($request, true);
        $user = $request->user('api');
        DB::transaction(function () use ($data, $user, $address): void {
            if ($data['is_default_shipping'] ?? false) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default_shipping' => false]);
            }
            if ($data['is_default_billing'] ?? false) {
                $user->addresses()->where('id', '!=', $address->id)->update(['is_default_billing' => false]);
            }
            $address->fill($data)->save();
        });

        return $this->successResponse($this->addressData($address->fresh()), 'Customer address updated successfully.');
    }

    public function deleteAddress(Request $request, string $id): JsonResponse
    {
        $address = $this->address($request, $id);
        $address->delete();

        return $this->noContentResponse('Customer address deleted successfully.');
    }

    public function setDefaultShipping(Request $request, string $id): JsonResponse
    {
        return $this->setDefaultAddress($request, $id, 'is_default_shipping');
    }

    public function setDefaultBilling(Request $request, string $id): JsonResponse
    {
        return $this->setDefaultAddress($request, $id, 'is_default_billing');
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $preference = NotificationPreference::query()->firstOrCreate([
            'user_id' => $request->user('api')->id,
            'business_id' => $business->id,
        ]);

        return $this->successResponse($preference->only([
            'email_order_updates', 'email_marketing', 'email_reviews', 'push_order_updates', 'push_marketing', 'sms_order_updates',
        ]), 'Notification preferences retrieved successfully.');
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'email_order_updates' => ['sometimes', 'boolean'],
            'email_marketing' => ['sometimes', 'boolean'],
            'email_reviews' => ['sometimes', 'boolean'],
            'push_order_updates' => ['sometimes', 'boolean'],
            'push_marketing' => ['sometimes', 'boolean'],
            'sms_order_updates' => ['sometimes', 'boolean'],
        ]);
        $preference = NotificationPreference::query()->firstOrNew([
            'user_id' => $request->user('api')->id,
            'business_id' => $business->id,
        ]);
        $preference->fill($data)->save();

        return $this->successResponse($preference->only([
            'email_order_updates', 'email_marketing', 'email_reviews', 'push_order_updates', 'push_marketing', 'sms_order_updates',
        ]), 'Notification preferences updated successfully.');
    }

    public function orders(Request $request): JsonResponse
    {
        $customer = $this->customer($request->user('api'));
        $business = $this->business($request);
        $paginator = Order::query()->forBusiness($business->id)->where('customer_id', $customer->id)
            ->with(['items', 'shipments'])->latest('created_at')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($paginator, 'Customer orders retrieved successfully.', fn (Order $order): array => (new OrderResource($order))->resolve($request));
    }

    public function order(Request $request, string $orderNumber): JsonResponse
    {
        $customer = $this->customer($request->user('api'));
        $order = Order::query()->forBusiness($this->business($request)->id)->where('customer_id', $customer->id)
            ->where('order_number', $orderNumber)->with(['items', 'shipments'])->firstOrFail();

        return $this->successResponse(new OrderResource($order), 'Customer order retrieved successfully.');
    }

    public function cancelOrder(Request $request, string $orderNumber, \App\Services\OrderLifecycleService $lifecycle): JsonResponse
    {
        $customer = $this->customer($request->user('api'));
        $order = Order::query()->forBusiness($this->business($request)->id)->where('customer_id', $customer->id)
            ->where('order_number', $orderNumber)->firstOrFail();
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:1000']]);
        $updated = $lifecycle->changeStatus($order, 'cancelled', $request->user('api'), $data['reason'] ?? 'Cancelled by customer.');

        return $this->successResponse(new OrderResource($updated), 'Customer order cancelled successfully.');
    }

    private function setDefaultAddress(Request $request, string $id, string $column): JsonResponse
    {
        $user = $request->user('api');
        $address = $this->address($request, $id);
        DB::transaction(function () use ($user, $address, $column): void {
            $user->addresses()->update([$column => false]);
            $address->update([$column => true]);
        });

        return $this->successResponse($this->addressData($address->fresh()), 'Default address updated successfully.');
    }

    private function addressDataFromRequest(Request $request, bool $partial = false): array
    {
        $required = fn (string $field): string => $partial ? 'sometimes' : 'required';

        return $request->validate([
            'label' => ['sometimes', 'string', 'max:40'],
            'first_name' => [$required('first_name'), 'string', 'max:100'],
            'last_name' => [$required('last_name'), 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'address_line_1' => [$required('address_line_1'), 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => [$required('city'), 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => [$required('country'), 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_default_shipping' => ['sometimes', 'boolean'],
            'is_default_billing' => ['sometimes', 'boolean'],
        ]);
    }

    private function address(Request $request, string $id): UserAddress
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'Address not found.', 404);
        }

        return $request->user('api')->addresses()->findOrFail((int) $id);
    }

    private function addressData(UserAddress $address): array
    {
        return $address->only([
            'id', 'label', 'first_name', 'last_name', 'phone', 'address_line_1', 'address_line_2',
            'city', 'state', 'country', 'postal_code', 'is_default_shipping', 'is_default_billing', 'created_at',
        ]);
    }

    private function customer(User $user): Customer
    {
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
