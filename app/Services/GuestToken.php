<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

final class GuestToken
{
    /** @return array{guest_token: string, session_id: string, expires_at: string} */
    public function issue(): array
    {
        $sessionId = (string) Str::uuid();
        $expiresAt = now()->addSeconds((int) config('shopline.guest_token_ttl', 7200))->timestamp;
        $payload = $this->encode($sessionId.'|'.$expiresAt);
        $signature = hash_hmac('sha256', $payload, $this->key());

        return [
            'guest_token' => $payload.'.'.$signature,
            'session_id' => $sessionId,
            'expires_at' => now()->setTimestamp($expiresAt)->toISOString(),
        ];
    }

    public function resolveSession(string $token): ?string
    {
        if (! str_contains($token, '.')) {
            return null;
        }

        [$payload, $signature] = explode('.', $token, 2);
        if (! hash_equals(hash_hmac('sha256', $payload, $this->key()), $signature)) {
            return null;
        }

        $decoded = $this->decode($payload);
        if ($decoded === null || ! str_contains($decoded, '|')) {
            return null;
        }

        [$sessionId, $expiresAt] = explode('|', $decoded, 2);
        if (! Str::isUuid($sessionId) || ! ctype_digit($expiresAt) || (int) $expiresAt < now()->timestamp) {
            return null;
        }

        return $sessionId;
    }

    private function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
