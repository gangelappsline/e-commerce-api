<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterBusinessRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeBusinessMail;
use App\Mail\WelcomeCustomerMail;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Services\EmailVerificationToken;
use App\Services\GuestToken;
use App\Exceptions\ApiException;
use App\Support\BaseController;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;
use Throwable;

final class AuthController extends BaseController
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        tags: ['Auth'],
        summary: 'Register a customer account',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'email', 'password', 'password_confirmation'], properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Alex Rivera'),
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex@example.com'),
            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'C0mmerce!Secure'),
            new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'C0mmerce!Secure'),
        ])),
        responses: [new OA\Response(response: 201, description: 'Customer account created', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess')), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->trim()->toString(),
                'email' => mb_strtolower($request->string('email')->toString()),
                'password' => $request->string('password')->toString(),
                'phone' => $request->input('phone'),
                'default_role' => 'customer',
            ]);
            $user->assignRole($this->ensureRole('customer'));
            $customer = Customer::create([
                'user_id' => $user->id,
                'accepts_marketing' => (bool) $request->boolean('accepts_marketing'),
            ]);

            return $user->load('roles');
        });

        $user->sendEmailVerificationNotification();
        Mail::to($user->email)->queue(new WelcomeCustomerMail(['name' => $user->name]));

        return $this->successResponse(
            $this->authPayload($user, 'Customer registration completed'),
            'Account created successfully. Please verify your email address.',
            201,
        );
    }

    #[OA\Post(
        path: '/api/v1/auth/register-business',
        tags: ['Auth'],
        summary: 'Register a business and its owner account',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['owner_name', 'business_name', 'business_type', 'email', 'password', 'password_confirmation'], properties: [
            new OA\Property(property: 'owner_name', type: 'string', example: 'Alex Rivera'),
            new OA\Property(property: 'business_name', type: 'string', example: 'Northwind Goods'),
            new OA\Property(property: 'business_type', type: 'string', example: 'home'),
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'owner@example.com'),
            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'C0mmerce!Secure'),
            new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'C0mmerce!Secure'),
        ])),
        responses: [new OA\Response(response: 201, description: 'Business registration created', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess')), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))],
    )]
    public function registerBusiness(RegisterBusinessRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['owner_name'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'default_role' => 'business',
            ]);
            $user->assignRole($this->ensureRole('customer'), $this->ensureRole('business'));

            $slug = $data['slug'] ?? Str::slug($data['business_name']);
            if ($slug === '') {
                $slug = 'store-'.Str::lower(Str::random(8));
            }
            if (! isset($data['slug']) && Business::query()->where('slug', $slug)->exists()) {
                $slug .= '-'.Str::lower(Str::random(6));
            }

            $business = Business::create([
                'owner_id' => $user->id,
                'name' => $data['business_name'],
                'slug' => $slug,
                'business_type' => $data['business_type'],
                'currency' => strtoupper($data['currency'] ?? 'USD'),
                'country' => $data['country'] ?? null,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'pending',
                'settings' => ['theme' => [], 'payments' => ['available' => []], 'shipping' => []],
            ]);

            $business->users()->attach($user->id, [
                'role' => 'owner',
                'joined_at' => now(),
                'is_active' => true,
            ]);

            Customer::create(['user_id' => $user->id, 'default_business_id' => $business->id]);
            $user->setRelation('registeredBusiness', $business);

            return $user->load('roles');
        });

        $user->sendEmailVerificationNotification();
        Mail::to($user->email)->queue(new WelcomeBusinessMail([
            'name' => $user->name,
            'store_name' => $user->getRelation('registeredBusiness')->name,
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/business/store',
        ]));

        return $this->successResponse(
            $this->authPayload($user, 'Business registration completed'),
            'Business registered successfully. It will be available after platform approval.',
            201,
        );
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        tags: ['Auth'],
        summary: 'Authenticate and issue a Passport bearer token',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password'], properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex@example.com'),
            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'C0mmerce!Secure'),
            new OA\Property(property: 'device_name', type: 'string', example: 'iOS app'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Token issued', content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess')), new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', mb_strtolower($data['email']))->first();

        if ($user === null || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            throw new ApiException('UNAUTHENTICATED', 'The supplied credentials are invalid.', 401);
        }

        $selectedBusiness = null;
        $storeSlug = $request->input('store') ?? $request->header('X-Store-Slug');
        if (is_string($storeSlug) && $storeSlug !== '') {
            $selectedBusiness = Business::query()->where('slug', $storeSlug)->where('status', 'active')->first();
            if ($selectedBusiness === null || (! $user->hasRole('admin', 'api') && ! $this->belongsToBusiness($user, $selectedBusiness))) {
                throw new ApiException('UNAUTHORIZED', 'The user does not belong to the requested store.', 403);
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $user->load('roles');

        return $this->successResponse(
            $this->authPayload($user, $request->input('device_name', 'ShopLine API'), $selectedBusiness),
            'Logged in successfully.',
        );
    }

    public function guestLogin(Request $request, GuestToken $guestToken): JsonResponse
    {
        return $this->successResponse($guestToken->issue(), 'Guest checkout session created.', 201);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user('api');
        if (! $user instanceof User) {
            throw new ApiException('UNAUTHENTICATED', 'Authentication is required.', 401);
        }

        $oldToken = $user->token();
        $payload = $this->authPayload($user->load('roles'), 'ShopLine API refresh');
        $oldToken?->revoke();

        return $this->successResponse($payload, 'Access token refreshed successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('api')?->token()?->revoke();

        return $this->noContentResponse('Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user('api')->load('roles')), 'Profile retrieved successfully.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'avatar' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ]);
        $user = $request->user('api');
        $user->fill($data)->save();

        return $this->successResponse(new UserResource($user->load('roles')), 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $user = $request->user('api');
        if (! Hash::check($data['current_password'], $user->password)) {
            throw new ApiException('VALIDATION_ERROR', 'The current password is incorrect.', 422, ['current_password' => ['The current password is incorrect.']]);
        }

        $currentTokenId = $user->token()?->id;
        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->where('id', '!=', $currentTokenId)->update(['revoked' => true]);

        return $this->noContentResponse('Password updated successfully.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        return $this->successResponse(null, 'If the account exists, a password reset email has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->update(['revoked' => true]);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The password reset link is invalid or expired.', 422, ['email' => [__($status)]]);
        }

        return $this->successResponse(null, 'Password reset successfully.');
    }

    public function verifyEmail(Request $request, EmailVerificationToken $tokens): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:1024']]);
        $user = $request->user('api');

        if (! $tokens->verify($user, $data['token'])) {
            throw new ApiException('UNAUTHORIZED', 'The email verification token is invalid or expired.', 403);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return $this->successResponse(new UserResource($user->load('roles')), 'Email address verified successfully.');
    }

    public function verifyEmailLink(Request $request, EmailVerificationToken $tokens): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'token' => ['required', 'string', 'max:1024'],
        ]);
        $user = User::query()->findOrFail($data['user_id']);

        if (! $tokens->verify($user, $data['token'])) {
            throw new ApiException('UNAUTHORIZED', 'The email verification token is invalid or expired.', 403);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return $this->successResponse(new UserResource($user->load('roles')), 'Email address verified successfully.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user('api');
        if ($user->hasVerifiedEmail()) {
            return $this->successResponse(null, 'Email address is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->successResponse(null, 'A verification email has been sent.');
    }

    public function switchStore(Request $request): JsonResponse
    {
        $data = $request->validate(['store_slug' => ['required', 'string', 'max:100']]);
        $business = Business::query()->where('slug', $data['store_slug'])->where('status', 'active')->first();
        $user = $request->user('api');

        if ($business === null) {
            throw new ApiException('STORE_NOT_FOUND', 'Store not found.', 404);
        }
        if (! $user->hasRole('admin', 'api') && ! $this->belongsToBusiness($user, $business)) {
            throw new ApiException('UNAUTHORIZED', 'You do not have access to this store.', 403);
        }

        $payload = $this->authPayload($user->load('roles'), 'ShopLine store switch');
        $payload['store'] = (new BusinessResource($business))->resolve($request);
        $user->token()?->revoke();

        return $this->successResponse($payload, 'Store context switched successfully.', 200, ['store' => $business->slug]);
    }

    private function authPayload(User $user, string $deviceName, ?Business $business = null): array
    {
        $scopes = ['storefront'];
        foreach (['customer', 'business', 'seller', 'admin'] as $role) {
            if ($user->hasRole($role, 'api')) {
                $scopes[] = $role;
            }
        }

        $result = $user->createToken($deviceName, array_values(array_unique($scopes)));

        return [
            'access_token' => $result->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $result->token->expires_at?->toISOString(),
            'scopes' => array_values(array_unique($scopes)),
            'user' => (new UserResource($user->loadMissing('roles')))->resolve(request()),
            'store' => $business === null ? null : (new BusinessResource($business))->resolve(request()),
        ];
    }

    private function belongsToBusiness(User $user, Business $business): bool
    {
        return (int) $business->owner_id === (int) $user->id || $business->users()
            ->where('users.id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function ensureRole(string $name): Role
    {
        return Role::findOrCreate($name, 'api');
    }
}
