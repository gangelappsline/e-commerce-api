<?php

declare(strict_types=1);

namespace App\Mail;

final class ReviewRequestMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.review-request';
    }

    protected function defaultSubject(): string
    {
        return 'How was your purchase?';
    }
}
