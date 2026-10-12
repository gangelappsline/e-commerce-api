<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBusinessScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        $tenant = $request->attributes->get('tenant');

        if ($user === null || $tenant === null) {
            throw new ApiException('UNAUTHORIZED', 'A valid user and store context are required.', 403);
        }

        if ($user->hasRole('admin', 'api')) {
            return $next($request);
        }

        $membership = $tenant->users()
            ->where('users.id', $user->getKey())
            ->wherePivot('is_active', true)
            ->first();

        if ($membership === null && (int) $tenant->owner_id !== (int) $user->getKey()) {
            throw new ApiException('UNAUTHORIZED', 'You do not have access to this store.', 403);
        }

        $request->attributes->set('business_membership', $membership?->pivot);

        return $next($request);
    }
}
