<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class QueuedMailable extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public array $data = [])
    {
    }

    abstract protected function template(): string;

    abstract protected function defaultSubject(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) ($this->data['subject'] ?? $this->defaultSubject()));
    }

    public function content(): Content
    {
        return new Content(view: $this->template(), with: ['data' => $this->data]);
    }
}
