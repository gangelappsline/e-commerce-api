<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserPassportGrantTest extends TestCase
{
    public function test_password_grant_requires_active_account_and_valid_credentials(): void
    {
        $user = new User();
        $user->forceFill([
            'password' => Hash::make('Strong-password-2026!'),
            'is_active' => true,
        ]);

        $this->assertTrue($user->validateForPassportPasswordGrant('Strong-password-2026!'));
        $this->assertFalse($user->validateForPassportPasswordGrant('incorrect-password'));

        $user->is_active = false;
        $this->assertFalse($user->validateForPassportPasswordGrant('Strong-password-2026!'));
    }
}
