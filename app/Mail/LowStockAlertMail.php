<?php

declare(strict_types=1);

namespace App\Mail;

final class LowStockAlertMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.low-stock-alert';
    }

    protected function defaultSubject(): string
    {
        return 'A product is running low on stock';
    }
}
