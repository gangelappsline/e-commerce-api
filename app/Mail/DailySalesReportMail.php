<?php

declare(strict_types=1);

namespace App\Mail;

final class DailySalesReportMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.daily-sales-report';
    }

    protected function defaultSubject(): string
    {
        return 'Your daily sales report';
    }
}
