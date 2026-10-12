<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Services\EmailVerificationToken;

final class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $token = app(EmailVerificationToken::class)->issue($notifiable);
        $url = rtrim((string) config('app.url'), '/').'/api/v1/auth/verify-email-link?user_id='
            .$notifiable->getKey().'&token='.urlencode($token);

        return (new MailMessage())
            ->subject('Verify your ShopLine email address')
            ->view('emails.verify-email', ['data' => ['name' => $notifiable->name, 'url' => $url]]);
    }
}
