<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject(__('Réinitialisation du mot de passe'))
            ->line(__('Vous recevez cet e-mail car une réinitialisation du mot de passe a été demandée pour votre compte.'))
            ->action(__('Réinitialiser le mot de passe'), $url)
            ->line(__('Ce lien expire dans 60 minutes. Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.'));
    }
}
