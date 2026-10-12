<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Models\BusinessUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBusinessManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->attributes->get('tenant');
        $user = $request->user('api');
        $membership = $request->attributes->get('business_membership');

        if ($business instanceof Business && $user !== null
            && ($user->hasRole('admin', 'api') || (int) $business->owner_id === (int) $user->id
                || ($membership instanceof BusinessUser && in_array($membership->role, ['owner', 'manager'], true)))) {
            return $next($request);
        }

        throw new ApiException('UNAUTHORIZED', 'Store owner or manager privileges are required.', 403);
    }
}
