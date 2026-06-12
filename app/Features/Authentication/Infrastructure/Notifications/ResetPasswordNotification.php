<?php

namespace App\Features\Authentication\Infrastructure\Notifications;

use App\Features\Users\Domain\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

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
        $resetUrl = config('app.frontend_url').'/account/password/reset/'.$uid.'/'.$this->token;

        return (new MailMessage)
            ->subject('Redefinição de Senha')
            ->greeting('Olá!')
            ->line('Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para a sua conta.')
            ->action('Redefinir Senha', $resetUrl)
            ->line('Este link de redefinição de senha expirará em 60 minutos.')
            ->line('Se você não solicitou uma redefinição de senha, nenhuma ação é necessária.')
            ->salutation('Atenciosamente, Trocado');
    }
}
