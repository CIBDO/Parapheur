<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccountCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $plainPassword,
    ) {}

    public function via(object $notifiable): array
    {
        // Mail uniquement : ne jamais stocker le mot de passe en clair en base.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'e-Parapheur');
        $loginUrl = url('/login');

        return (new MailMessage)
            ->subject("[{$appName}] Vos identifiants de connexion")
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Un compte a été créé pour vous sur le parapheur électronique DGTCP.')
            ->line('Voici vos informations de connexion :')
            ->line('**Adresse e-mail :** '.$notifiable->email)
            ->line('**Mot de passe temporaire :** '.$this->plainPassword)
            ->action('Se connecter', $loginUrl)
            ->line('Pour des raisons de sécurité, changez ce mot de passe après votre première connexion.')
            ->line('Ceci est un message automatique — merci de ne pas y répondre.');
    }
}
