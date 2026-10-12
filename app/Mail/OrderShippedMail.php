<?php

declare(strict_types=1);

namespace App\Mail;

final class OrderShippedMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.order-shipped';
    }

    protected function defaultSubject(): string
    {
        return 'Your order has shipped';
    }
}
