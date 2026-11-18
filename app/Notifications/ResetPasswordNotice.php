<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotice extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
        $minutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Reset your HRMS password')
            ->greeting('Hello '.$notifiable->name)
            ->line('We received a request to reset the password for this staff account.')
            ->action('Choose a new password', $url)
            ->line('This link works for '.$minutes.' minutes and can be used once.')
            ->line('If you did not ask for this, you can ignore this email. Your password stays the same.');
    }
}
