<?php

declare(strict_types=1);

namespace App\Mail;

final class OrderRefundedMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.order-refunded';
    }

    protected function defaultSubject(): string
    {
        return 'Your refund update';
    }
}
