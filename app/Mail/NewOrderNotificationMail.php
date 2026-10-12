<?php

declare(strict_types=1);

namespace App\Mail;

final class NewOrderNotificationMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.new-order-notification';
    }

    protected function defaultSubject(): string
    {
        return 'A new order was placed';
    }
}
