<?php

declare(strict_types=1);

namespace App\Mail;

final class PasswordResetMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.password-reset';
    }

    protected function defaultSubject(): string
    {
        return 'Reset your password';
    }
}
