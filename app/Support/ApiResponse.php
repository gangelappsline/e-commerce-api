<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;

trait ApiResponse
{
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation completed successfully',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->normalizeData($data),
            'meta' => array_merge($this->baseMeta(), $meta),
        ], $status);
    }

    protected function errorResponse(
        string $code,
        string $message,
        array $details = [],
        int $status = 400,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => $code,
                'details' => (object) $details,
            ],
            'meta' => array_merge($this->baseMeta(), $meta),
        ], $status);
    }

    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message,
        ?callable $transform = null,
        array $meta = [],
    ): JsonResponse {
        $items = collect($paginator->items());

        if ($transform !== null) {
            $items = $items->map($transform);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->normalizeData($items),
            'meta' => array_merge($this->baseMeta(), [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ], $meta),
        ]);
    }

    /** A body is retained to honor the common response envelope. */
    protected function noContentResponse(string $message = 'Operation completed successfully', array $meta = []): JsonResponse
    {
        return $this->successResponse(null, $message, 200, $meta);
    }

    protected function baseMeta(): array
    {
        $request = request();
        $meta = [
            'timestamp' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
            'request_id' => $request->attributes->get('request_id', (string) Str::uuid()),
        ];

        $tenant = $request->attributes->get('tenant');
        if ($tenant !== null) {
            $meta['store'] = $tenant->slug;
        }

        return $meta;
    }

    private function normalizeData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        if ($data instanceof Collection) {
            return $data->map(fn (mixed $value): mixed => $this->normalizeData($value))->values()->all();
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->normalizeData($value);
            }

            return $data;
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        return $data;
    }
}
