<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\BaseController;
use Illuminate\Http\JsonResponse;

final class BusinessTypeController extends BaseController
{
    public function index(): JsonResponse
    {
        $types = collect(config('shopline.supported_business_types'))->map(fn (string $value): array => [
            'value' => $value,
            'label' => str($value)->replace('_', ' ')->title()->toString(),
        ]);

        return $this->successResponse($types, 'Business types retrieved successfully.');
    }
}
