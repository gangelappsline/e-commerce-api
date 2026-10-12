<?php

declare(strict_types=1);

namespace App\Mail;

final class ContactFormMail extends QueuedMailable
{
    protected function template(): string
    {
        return 'emails.contact-form';
    }

    protected function defaultSubject(): string
    {
        return 'A new customer message was received';
    }
}
