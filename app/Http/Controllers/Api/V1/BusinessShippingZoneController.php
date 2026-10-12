<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Business;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class BusinessShippingZoneController extends TenantCrudController
{
    protected string $modelClass = ShippingZone::class;
    protected array $responseFields = ['id', 'name', 'countries', 'states', 'postal_codes', 'is_active', 'created_at', 'updated_at'];

    protected function rulesFor(Request $request, Business $business, ?int $id = null): array
    {
        return [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'countries' => ['sometimes', 'nullable', 'array', 'max:500'],
            'countries.*' => ['string', 'max:100'],
            'states' => ['sometimes', 'nullable', 'array', 'max:500'],
            'states.*' => ['string', 'max:100'],
            'postal_codes' => ['sometimes', 'nullable', 'array', 'max:1000'],
            'postal_codes.*' => ['string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function query(Business $business): Builder
    {
        return ShippingZone::query()->forBusiness($business->id);
    }
}
