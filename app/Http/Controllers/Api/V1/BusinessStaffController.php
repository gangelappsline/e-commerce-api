<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Mail\WelcomeBusinessMail;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class BusinessStaffController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $staff = $business->users()->wherePivot('is_active', true)->orderBy('users.name')->get();

        return $this->successResponse($staff->map(fn (User $user): array => $this->staffData($user)), 'Business staff retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanManageStaff($request);
        $business = $this->business($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['manager', 'seller', 'staff'])],
            'permissions' => ['sometimes', 'nullable', 'array', 'max:30'],
            'permissions.*' => ['string', Rule::in(['catalog.manage', 'inventory.manage', 'orders.manage', 'orders.fulfill', 'customers.manage', 'reports.view', 'reviews.moderate'])],
        ]);
        $result = DB::transaction(function () use ($data, $business): array {
            $email = mb_strtolower($data['email']);
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            $isNew = $user === null;
            if ($user === null) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $email,
                    'password' => Str::random(48).'aA1!',
                    'default_role' => $data['role'] === 'manager' ? 'business' : 'seller',
                ]);
            }
            $existing = BusinessUser::query()->where('business_id', $business->id)->where('user_id', $user->id)->first();
            if ($existing !== null) {
                throw new ApiException('CONFLICT', 'This user is already a member of the store.', 409);
            }
            $business->users()->attach($user->id, [
                'role' => $data['role'],
                'permissions' => $data['permissions'] ?? null,
                'joined_at' => now(),
                'is_active' => true,
            ]);
            $spatieRole = $data['role'] === 'manager' ? 'business' : 'seller';
            $role = Role::findOrCreate($spatieRole, 'api');
            if (! $user->hasRole($spatieRole, 'api')) {
                $user->assignRole($role);
            }

            return [$user->fresh(), $isNew];
        });
        [$user, $isNew] = $result;
        if ($isNew) {
            $user->sendEmailVerificationNotification();
            Password::sendResetLink(['email' => $user->email]);
        }
        Mail::to($user->email)->queue(new WelcomeBusinessMail([
            'name' => $user->name,
            'store_name' => $business->name,
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/auth/login',
        ]));

        return $this->successResponse($this->staffData($business->users()->where('users.id', $user->id)->firstOrFail()), 'Staff member invited successfully.', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $this->findStaff($request, $id);

        return $this->successResponse($this->staffData($user), 'Staff member retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->assertCanManageStaff($request);
        $business = $this->business($request);
        $user = $this->findStaff($request, $id);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'role' => ['sometimes', 'required', Rule::in(['manager', 'seller', 'staff'])],
            'permissions' => ['sometimes', 'nullable', 'array', 'max:30'],
            'permissions.*' => ['string', Rule::in(['catalog.manage', 'inventory.manage', 'orders.manage', 'orders.fulfill', 'customers.manage', 'reports.view', 'reviews.moderate'])],
        ]);
        DB::transaction(function () use ($business, $user, $data): void {
            if (isset($data['name'])) {
                $user->update(['name' => $data['name']]);
            }
            $membership = BusinessUser::query()->where('business_id', $business->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $pivotUpdate = [];
            if (isset($data['role'])) {
                $pivotUpdate['role'] = $data['role'];
            }
            if (array_key_exists('permissions', $data)) {
                $pivotUpdate['permissions'] = $data['permissions'];
            }
            $membership->update($pivotUpdate);
        });

        return $this->successResponse($this->staffData($business->users()->where('users.id', $user->id)->firstOrFail()), 'Staff member updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->assertCanManageStaff($request);
        $business = $this->business($request);
        $user = $this->findStaff($request, $id);
        if ((int) $business->owner_id === (int) $user->id) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The store owner cannot be removed from their store.', 422);
        }
        $business->users()->detach($user->id);

        return $this->noContentResponse('Staff member removed successfully.');
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $this->assertCanManageStaff($request);
        $business = $this->business($request);
        $user = $this->findStaff($request, $id);
        $data = $request->validate([
            'role' => ['required', Rule::in(['manager', 'seller', 'staff'])],
            'permissions' => ['sometimes', 'nullable', 'array', 'max:30'],
            'permissions.*' => ['string', Rule::in(['catalog.manage', 'inventory.manage', 'orders.manage', 'orders.fulfill', 'customers.manage', 'reports.view', 'reviews.moderate'])],
        ]);
        BusinessUser::query()->where('business_id', $business->id)->where('user_id', $user->id)->firstOrFail()->update([
            'role' => $data['role'],
            'permissions' => $data['permissions'] ?? null,
        ]);

        return $this->successResponse($this->staffData($business->users()->where('users.id', $user->id)->firstOrFail()), 'Staff role assigned successfully.');
    }

    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $this->assertCanManageStaff($request);
        $business = $this->business($request);
        $user = $this->findStaff($request, $id);
        if ((int) $business->owner_id === (int) $user->id) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The store owner status cannot be changed here.', 422);
        }
        $membership = BusinessUser::query()->where('business_id', $business->id)->where('user_id', $user->id)->firstOrFail();
        $membership->update(['is_active' => ! $membership->is_active]);

        return $this->successResponse($this->staffData($business->users()->where('users.id', $user->id)->firstOrFail()), 'Staff member status updated successfully.');
    }

    private function findStaff(Request $request, string $id): User
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'Staff member not found.', 404);
        }
        $business = $this->business($request);

        return $business->users()->where('users.id', (int) $id)->firstOrFail();
    }

    private function staffData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->pivot?->role,
            'permissions' => $user->pivot?->permissions,
            'is_active' => (bool) $user->pivot?->is_active,
            'joined_at' => $user->pivot?->joined_at,
            'email_verified_at' => $user->email_verified_at,
        ];
    }

    private function assertCanManageStaff(Request $request): void
    {
        $business = $this->business($request);
        $user = $request->user('api');
        $membership = $request->attributes->get('business_membership');
        if ($user?->hasRole('admin', 'api') || (int) $business->owner_id === (int) $user?->id) {
            return;
        }
        if (! $membership instanceof BusinessUser || ! in_array($membership->role, ['owner', 'manager'], true)) {
            throw new ApiException('UNAUTHORIZED', 'Only store owners and managers can manage staff.', 403);
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
