<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/').'/api/v1/auth/reset-password?token='.urlencode($this->token)
            .'&email='.urlencode((string) $notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject('Reset your ShopLine password')
            ->view('emails.password-reset', ['data' => ['name' => $notifiable->name, 'url' => $url]]);
    }
}
