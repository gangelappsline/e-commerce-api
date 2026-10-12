<?php

declare(strict_types=1);

namespace App\Mail;

final class OrderCancelledMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.order-cancelled';
    }

    protected function defaultSubject(): string
    {
        return 'Your order was cancelled';
    }
}
