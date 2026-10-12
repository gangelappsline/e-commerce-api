<?php

declare(strict_types=1);

namespace App\Mail;

final class WelcomeBusinessMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.welcome-business';
    }

    protected function defaultSubject(): string
    {
        return 'Your ShopLine store is getting started';
    }
}
