<?php

declare(strict_types=1);

namespace App\Mail;

final class VerifyEmailMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.verify-email';
    }

    protected function defaultSubject(): string
    {
        return 'Verify your email address';
    }
}
