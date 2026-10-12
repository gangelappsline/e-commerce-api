<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

final class EmailVerificationToken
{
    public function issue(User $user): string
    {
        $expiresAt = now()->addHours(24)->timestamp;
        $signature = hash_hmac('sha256', $this->payload($user, $expiresAt), $this->key());

        return $this->encode($expiresAt.'.'.$signature);
    }

    public function verify(User $user, string $token): bool
    {
        $decoded = $this->decode($token);
        if ($decoded === null || ! str_contains($decoded, '.')) {
            return false;
        }

        [$expiresAt, $signature] = explode('.', $decoded, 2);
        if (! ctype_digit($expiresAt) || (int) $expiresAt < now()->timestamp) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $this->payload($user, (int) $expiresAt), $this->key()),
            $signature,
        );
    }

    private function payload(User $user, int $expiresAt): string
    {
        return $user->getKey().'|'.$user->getEmailForVerification().'|'.$expiresAt;
    }

    private function key(): string
    {
        return (string) config('app.key');
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
