<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BaseController;
use Tests\TestCase;

final class ApiResponseTest extends TestCase
{
    public function test_success_responses_have_the_required_envelope(): void
    {
        $controller = new class extends BaseController
        {
            public function response(): \Illuminate\Http\JsonResponse
            {
                return $this->successResponse(['id' => 7], 'Resource retrieved successfully.');
            }
        };

        $response = $controller->response();
        $body = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertSame('Resource retrieved successfully.', $body['message']);
        $this->assertSame(['id' => 7], $body['data']);
        $this->assertArrayHasKey('timestamp', $body['meta']);
        $this->assertArrayHasKey('request_id', $body['meta']);
    }

    public function test_empty_success_responses_preserve_the_envelope(): void
    {
        $controller = new class extends BaseController
        {
            public function response(): \Illuminate\Http\JsonResponse
            {
                return $this->noContentResponse('Removed successfully.');
            }
        };

        $body = $controller->response()->getData(true);

        $this->assertTrue($body['success']);
        $this->assertNull($body['data']);
        $this->assertSame('Removed successfully.', $body['message']);
    }
}
