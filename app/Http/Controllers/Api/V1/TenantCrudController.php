<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Models\Business;
use App\Support\BaseController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

abstract class TenantCrudController extends BaseController
{
    /** @var class-string<Model> */
    protected string $modelClass;

    /** @var list<string> */
    protected array $responseFields = ['id', 'name', 'slug', 'created_at', 'updated_at'];

    abstract protected function rulesFor(Request $request, Business $business, ?int $id = null): array;

    protected function query(Business $business): Builder
    {
        $class = $this->modelClass;

        return $class::query()->where('business_id', $business->id);
    }

    public function index(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $perPage = min(max((int) $request->integer('per_page', 15), 1), (int) config('shopline.max_page_size', 100));
        $paginator = $this->query($business)->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($paginator, 'Records retrieved successfully.', fn (Model $item): array => $item->only($this->responseFields));
    }

    public function store(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = Validator::make($request->all(), $this->rulesFor($request, $business))->validate();
        $class = $this->modelClass;
        $record = new $class();
        $record->forceFill(Arr::only($data, array_diff($record->getFillable(), ['business_id'])));
        $record->business_id = $business->id;
        $record->save();

        return $this->successResponse($record->only($this->responseFields), 'Record created successfully.', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $record = $this->findRecord($request, $id);

        return $this->successResponse($record->only($this->responseFields), 'Record retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $business = $this->business($request);
        $record = $this->findRecord($request, $id);
        $data = Validator::make($request->all(), $this->rulesFor($request, $business, (int) $record->getKey()))->validate();
        $record->fill(Arr::only($data, array_diff($record->getFillable(), ['business_id'])))->save();

        return $this->successResponse($record->fresh()->only($this->responseFields), 'Record updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findRecord($request, $id)->delete();

        return $this->noContentResponse('Record deleted successfully.');
    }

    protected function findRecord(Request $request, string $id): Model
    {
        if (! ctype_digit($id)) {
            throw new ApiException('NOT_FOUND', 'The requested resource was not found.', 404);
        }

        return $this->query($this->business($request))->findOrFail((int) $id);
    }

    protected function business(Request $request): Business
    {
        $business = $request->attributes->get('tenant');
        if (! $business instanceof Business) {
            throw new ApiException('STORE_NOT_FOUND', 'Store context is required.', 404);
        }

        return $business;
    }
}
