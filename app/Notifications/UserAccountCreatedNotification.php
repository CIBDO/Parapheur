<?php

namespace App\Notifications;

use App\Support\NotificationPresentation;
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
        $appName = config('app.name', 'E-Tresor');
        $loginUrl = url('/login');

        return (new MailMessage)
            ->subject("[{$appName}] Vos identifiants de connexion")
            ->greeting(NotificationPresentation::greeting((string) ($notifiable->name ?? '')))
            ->line('Un compte a été créé pour vous sur le Bureau Numérique de la Direction Générale du Trésor et de la Comptabilité Publique (DGTCP).')
            ->line('Voici vos informations de connexion :')
            ->line('**Adresse e-mail :** '.$notifiable->email)
            ->line('**Mot de passe temporaire :** '.$this->plainPassword)
            ->action('Se connecter', $loginUrl)
            ->line('Pour des raisons de sécurité, vous devrez obligatoirement modifier ce mot de passe lors de votre première connexion.')
            ->line(NotificationPresentation::FOOTER);
    }
}