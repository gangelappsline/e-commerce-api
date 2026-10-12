<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureBusinessManager;
use App\Http\Middleware\EnsureBusinessScope;
use App\Http\Middleware\OptionalPassportAuth;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\ResolveTenant;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [RequestId::class]);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'business.scope' => EnsureBusinessScope::class,
            'business.manager' => EnsureBusinessManager::class,
            'admin' => EnsureAdmin::class,
            'auth.optional' => OptionalPassportAuth::class,
        ]);
        $middleware->throttleApi('api-public');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            return ApiExceptionRenderer::render($exception, $request);
        });
    })
    ->create();
