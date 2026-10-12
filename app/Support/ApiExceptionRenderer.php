<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $status = 500;
        $code = 'SERVER_ERROR';
        $message = 'An unexpected error occurred.';
        $details = [];

        if ($exception instanceof ApiException) {
            $status = $exception->httpStatus;
            $code = $exception->errorCode;
            $message = $exception->getMessage();
            $details = $exception->details;
        } elseif ($exception instanceof ValidationException) {
            $status = 422;
            $code = 'VALIDATION_ERROR';
            $message = 'Validation failed.';
            $details = $exception->errors();
        } elseif ($exception instanceof AuthenticationException) {
            $status = 401;
            $code = 'UNAUTHENTICATED';
            $message = 'Authentication is required.';
        } elseif ($exception instanceof AuthorizationException) {
            $status = 403;
            $code = 'UNAUTHORIZED';
            $message = 'You are not authorized to perform this action.';
        } elseif ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            $status = 404;
            $code = 'NOT_FOUND';
            $message = 'The requested resource was not found.';
        } elseif ($exception instanceof TooManyRequestsHttpException) {
            $status = 429;
            $code = 'RATE_LIMIT_EXCEEDED';
            $message = 'Too many requests. Please try again later.';
        } elseif ($exception instanceof InvalidSignatureException) {
            $status = 403;
            $code = 'UNAUTHORIZED';
            $message = 'The supplied signature is invalid or has expired.';
        } elseif ($exception instanceof QueryException && self::isUniqueConstraintViolation($exception)) {
            $status = 409;
            $code = 'CONFLICT';
            $message = 'The requested resource conflicts with existing data.';
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'UNAUTHORIZED',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                422 => 'VALIDATION_ERROR',
                429 => 'RATE_LIMIT_EXCEEDED',
                default => $status >= 500 ? 'SERVER_ERROR' : 'BUSINESS_LOGIC_ERROR',
            };
            $message = $status >= 500 ? 'An unexpected error occurred.' : 'The request could not be completed.';
        }

        if ($status >= 500) {
            report($exception);
        }

        $meta = self::meta($request);
        $response = response()->json([
            'success' => false,
            'message' => $message,
            'error' => ['code' => $code, 'details' => (object) $details],
            'meta' => $meta,
        ], $status);
        $response->headers->set('X-Request-ID', (string) $meta['request_id']);

        return $response;
    }

    private static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' && in_array($driverCode, [1062, 19], true);
    }

    private static function meta(Request $request): array
    {
        $meta = [
            'timestamp' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
            'request_id' => $request->attributes->get('request_id', (string) \Illuminate\Support\Str::uuid()),
        ];

        if ($tenant = $request->attributes->get('tenant')) {
            $meta['store'] = $tenant->slug;
        }

        return $meta;
    }
}
