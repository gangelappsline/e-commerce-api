<?php

declare(strict_types=1);

namespace App\Mail;

final class OrderDeliveredMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.order-delivered';
    }

    protected function defaultSubject(): string
    {
        return 'Your order was delivered';
    }
}
