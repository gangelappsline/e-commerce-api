<?php

declare(strict_types=1);

namespace App\Mail;

final class WelcomeCustomerMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.welcome-customer';
    }

    protected function defaultSubject(): string
    {
        return 'Welcome to ShopLine';
    }
}
