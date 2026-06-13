<?php

namespace App\Features\Authentication\Infrastructure\Notifications;

use App\Features\Users\Domain\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(
        private readonly string $token,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $uid = $notifiable->id;
        $resetUrl = rtrim((string) config('app.frontend_url'), '/').'/account/password/reset/'.$uid.'/'.$this->token;

        return (new MailMessage)
            ->subject('Redefinição de Senha')
            ->markdown('mail.auth.reset-password', [
                'resetUrl' => $resetUrl,
            ]);
    }
}
