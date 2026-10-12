<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('api')?->hasRole('admin', 'api')) {
            return $next($request);
        }

        throw new ApiException('UNAUTHORIZED', 'Platform administrator privileges are required.', 403);
    }
}
