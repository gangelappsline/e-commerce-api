<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request);

        if ($tenant === null) {
            throw new ApiException('STORE_NOT_FOUND', 'Store not found.', 404);
        }

        if ($tenant->status !== 'active') {
            throw new ApiException('STORE_INACTIVE', 'This store is not currently available.', 403);
        }

        $request->attributes->set('tenant', $tenant);
        app()->instance('currentBusiness', $tenant);

        return $next($request);
    }

    private function resolve(Request $request): ?Business
    {
        $slug = trim((string) ($request->header('X-Store-Slug') ?? $request->query('store', '')));
        $host = strtolower((string) $request->getHost());
        $platformDomain = strtolower((string) config('shopline.platform_domain'));

        if ($slug === '' && $platformDomain !== '' && $host !== $platformDomain && str_ends_with($host, '.'.$platformDomain)) {
            $subdomain = substr($host, 0, -strlen('.'.$platformDomain));
            if (! str_contains($subdomain, '.')) {
                $slug = $subdomain;
            }
        }

        if ($slug !== '') {
            return Business::query()->where('slug', $slug)->first();
        }

        return Business::query()->whereRaw('LOWER(custom_domain) = ?', [$host])->first();
    }
}
