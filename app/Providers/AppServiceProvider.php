<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind application contracts here; payment providers remain intentionally decoupled.
    }

    public function boot(): void
    {
        date_default_timezone_set('UTC');

        Passport::enablePasswordGrant();
        Passport::tokensCan([
            'customer' => 'Access customer profile and storefront resources',
            'business' => 'Manage an owned business store',
            'seller' => 'Manage assigned store operations',
            'admin' => 'Manage the ShopLine platform',
            'storefront' => 'Access public storefront checkout resources',
        ]);
        Passport::setDefaultScope(['storefront']);
        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addDays(30));

        RateLimiter::for('api-public', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) $request->ip());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $identity = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($identity);
        });

        User::creating(function (User $user): void {
            $user->is_active ??= true;
        });
    }
}
