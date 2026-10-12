<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GuestToken;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class GuestTokenTest extends TestCase
{
    public function test_issued_guest_token_is_bound_to_a_server_generated_session(): void
    {
        $issued = app(GuestToken::class)->issue();

        $this->assertArrayHasKey('guest_token', $issued);
        $this->assertArrayHasKey('session_id', $issued);
        $this->assertTrue(str_contains($issued['guest_token'], '.'));
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/i', $issued['session_id']));
        $this->assertSame($issued['session_id'], app(GuestToken::class)->resolveSession($issued['guest_token']));
    }

    public function test_guest_token_rejects_tampering_and_expiration(): void
    {
        $tokens = app(GuestToken::class);
        $issued = $tokens->issue();
        $tampered = substr($issued['guest_token'], 0, -1).($issued['guest_token'][strlen($issued['guest_token']) - 1] === 'a' ? 'b' : 'a');

        $this->assertNull($tokens->resolveSession($tampered));

        Carbon::setTestNow(now()->addSeconds((int) config('shopline.guest_token_ttl', 7200) + 1));
        try {
            $this->assertNull($tokens->resolveSession($issued['guest_token']));
        } finally {
            Carbon::setTestNow();
        }
    }
}
