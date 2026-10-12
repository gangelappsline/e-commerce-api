<?php

declare(strict_types=1);

namespace App\Mail;

final class SubscriptionPaymentFailedMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.subscription-payment-failed';
    }

    protected function defaultSubject(): string
    {
        return 'Subscription payment needs attention';
    }
}
