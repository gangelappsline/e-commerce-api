<?php

declare(strict_types=1);

namespace App\Mail;

final class OrderStatusChangedMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.order-status-changed';
    }

    protected function defaultSubject(): string
    {
        return 'An update about your order';
    }
}
