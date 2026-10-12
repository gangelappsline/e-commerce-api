<?php

declare(strict_types=1);

namespace App\Mail;

final class AbandonedCartMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.abandoned-cart';
    }

    protected function defaultSubject(): string
    {
        return 'Your cart is waiting for you';
    }
}
