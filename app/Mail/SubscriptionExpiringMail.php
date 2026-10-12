<?php

declare(strict_types=1);

namespace App\Mail;

final class SubscriptionExpiringMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.subscription-expiring';
    }

    protected function defaultSubject(): string
    {
        return 'Your subscription is expiring soon';
    }
}
