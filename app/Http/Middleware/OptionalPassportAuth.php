<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OptionalPassportAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            $guard = auth('api');
            if (! $guard->check()) {
                throw new AuthenticationException('The supplied access token is invalid or expired.');
            }

            $user = $guard->user();
            if ($user === null || ! $user->is_active) {
                $user?->token()?->revoke();
                throw new AuthenticationException('The account associated with this access token is inactive.');
            }
        }

        return $next($request);
    }
}
