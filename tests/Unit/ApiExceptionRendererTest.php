<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ApiExceptionRenderer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ApiExceptionRendererTest extends TestCase
{
    public function test_validation_errors_use_the_standard_error_envelope(): void
    {
        $validator = Validator::make([], ['email' => ['required']]);
        try {
            $validator->validate();
            $this->fail('The validator should fail.');
        } catch (ValidationException $exception) {
            $request = Request::create('/api/v1/auth/register', 'POST');
            $request->attributes->set('request_id', 'd1a5b4e0-76e5-4cc5-b68a-627bb34a8bb1');
            $response = ApiExceptionRenderer::render($exception, $request);
        }

        $body = $response->getData(true);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('VALIDATION_ERROR', $body['error']['code']);
        $this->assertArrayHasKey('email', $body['error']['details']);
        $this->assertSame('d1a5b4e0-76e5-4cc5-b68a-627bb34a8bb1', $body['meta']['request_id']);
    }
}
