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
            ->subject(NotificationPresentation::mailSubject('Compte', 'Ouverture de votre accès au Bureau Numérique'))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line(
                'Un compte a été ouvert à votre nom sur le Bureau Numérique de la Direction Générale '
                .'du Trésor et de la Comptabilité Publique (DGTCP), afin de vous permettre d’accéder '
                .'aux services numériques institutionnels.'
            )
            ->line('Identifiants provisoires de connexion :')
            ->line('Adresse e-mail : '.$notifiable->email)
            ->line('Mot de passe temporaire : '.$this->plainPassword)
            ->line(NotificationPresentation::actionExpected(
                'vous connecter sans délai et procéder au changement obligatoire de ce mot de passe temporaire.'
            ))
            ->action('Se connecter', $loginUrl)
            ->line(NotificationPresentation::FOOTER);
    }
}