<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Models\User;
use App\Support\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminController extends BaseController
{
    public function businesses(Request $request): JsonResponse
    {
        $query = Business::query()->with('owner:id,name,email');
        if ($request->filled('status')) {
            $request->validate(['status' => ['required', 'in:pending,active,inactive,suspended']]);
            $query->where('status', $request->query('status'));
        }
        $paginator = $query->latest()->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return $this->paginatedResponse($paginator, 'Platform businesses retrieved successfully.', fn (Business $business): array => [
            ...(new BusinessResource($business))->resolve($request),
            'owner' => $business->owner?->only(['id', 'name', 'email']),
        ]);
    }

    public function updateBusinessStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,inactive,suspended,pending']]);
        $business = Business::query()->findOrFail($id);
        $business->forceFill([
            'status' => $data['status'],
            'is_verified' => $data['status'] === 'active' ? true : $business->is_verified,
            'verified_at' => $data['status'] === 'active' ? ($business->verified_at ?? now()) : $business->verified_at,
        ])->save();

        return $this->successResponse(new BusinessResource($business->fresh()), 'Business status updated successfully.');
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with('roles')->orderByDesc('id');
        if ($request->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_substr((string) $request->query('q'), 0, 120)).'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }
        $paginator = $query->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return $this->paginatedResponse($paginator, 'Platform users retrieved successfully.', fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'email_verified_at' => $user->email_verified_at,
            'roles' => $user->roles->pluck('name')->all(),
            'created_at' => $user->created_at,
        ]);
    }

    public function updateUserStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $user = User::query()->findOrFail($id);
        if ((int) $user->id === (int) $request->user('api')->id && ! $data['is_active']) {
            throw new ApiException('BUSINESS_LOGIC_ERROR', 'The current administrator cannot deactivate their own account.', 422);
        }
        DB::transaction(function () use ($user, $data): void {
            $user->update(['is_active' => $data['is_active']]);
            if (! $data['is_active']) {
                $user->tokens()->update(['revoked' => true]);
            }
        });

        return $this->successResponse(['id' => $user->id, 'is_active' => $user->fresh()->is_active], 'User status updated successfully.');
    }
}
