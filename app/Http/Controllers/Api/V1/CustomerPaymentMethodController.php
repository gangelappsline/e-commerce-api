<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CustomerPaymentMethodController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $methods = $this->customer($request)->paymentMethods()->latest()->get();

        return $this->successResponse($methods->map(fn (PaymentMethod $method): array => $this->safeMethod($method)), 'Payment methods retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:credit_card,debit_card,paypal,wallet,bank_account'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:80'],
            'last_four' => ['sometimes', 'nullable', 'digits:4'],
            'expiry_month' => ['sometimes', 'nullable', 'integer', 'between:1,12'],
            'expiry_year' => ['sometimes', 'nullable', 'integer', 'min:2026', 'max:2200'],
            // This must be a gateway-issued token. PAN and CVV values are deliberately not accepted.
            'token' => ['required', 'string', 'max:2048'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $customer = $this->customer($request);
        $method = DB::transaction(function () use ($data, $customer): PaymentMethod {
            if (($data['is_default'] ?? false) === true || ! $customer->paymentMethods()->exists()) {
                $customer->paymentMethods()->update(['is_default' => false]);
                $data['is_default'] = true;
            }

            return $customer->paymentMethods()->create($data);
        });

        return $this->successResponse($this->safeMethod($method), 'Payment method saved successfully.', 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $method = $this->customer($request)->paymentMethods()->findOrFail($id);
        $wasDefault = $method->is_default;
        $method->delete();
        if ($wasDefault) {
            $this->customer($request)->paymentMethods()->latest()->first()?->update(['is_default' => true]);
        }

        return $this->noContentResponse('Payment method deleted successfully.');
    }

    public function setDefault(Request $request, string $id): JsonResponse
    {
        $customer = $this->customer($request);
        $method = $customer->paymentMethods()->findOrFail($id);
        DB::transaction(function () use ($customer, $method): void {
            $customer->paymentMethods()->update(['is_default' => false]);
            $method->update(['is_default' => true]);
        });

        return $this->successResponse($this->safeMethod($method->fresh()), 'Default payment method updated successfully.');
    }

    private function safeMethod(PaymentMethod $method): array
    {
        return $method->only(['id', 'type', 'provider', 'last_four', 'expiry_month', 'expiry_year', 'is_default', 'created_at']);
    }

    private function customer(Request $request): Customer
    {
        $user = $request->user('api');
        if ($user === null) {
            throw new ApiException('UNAUTHENTICATED', 'Authentication is required.', 401);
        }

        return $user->customer()->firstOrCreate(['user_id' => $user->id]);
    }
}
