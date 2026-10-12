<?php

declare(strict_types=1);

namespace App\Mail;

final class NewReviewNotificationMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.new-review-notification';
    }

    protected function defaultSubject(): string
    {
        return 'A new product review was submitted';
    }
}
