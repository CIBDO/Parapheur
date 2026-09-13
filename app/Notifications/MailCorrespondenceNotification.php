<?php

namespace App\Notifications;

use App\Models\Correspondence;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MailCorrespondenceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Correspondence $correspondence,
        public string $event,
        public string $message,
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->correspondence->arrival_number
            ?: $this->correspondence->departure_number
            ?: '#'.$this->correspondence->id;

        $url = url($this->frontendPath());

        return (new MailMessage)
            ->subject('[Courrier] '.$this->title())
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message)
            ->line('Référence : '.$number)
            ->line('Objet : '.$this->correspondence->subject)
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Par : '.$this->actorName))
            ->action('Ouvrir le courrier', $url)
            ->line('Notification automatique — Bureau Numérique DGTCP.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message,
            'event' => $this->event,
            'correspondence_id' => $this->correspondence->id,
            'arrival_number' => $this->correspondence->arrival_number,
            'departure_number' => $this->correspondence->departure_number,
            'subject' => $this->correspondence->subject,
            'actor' => $this->actorName,
            'url' => $this->frontendPath(),
        ];
    }

    private function frontendPath(): string
    {
        $id = $this->correspondence->id;

        return match ($this->correspondence->direction?->value) {
            'sortant' => '/courrier/sortants/'.$id,
            'interne' => '/courrier/internes/'.$id,
            default => '/courrier/entrants/'.$id,
        };
    }

    private function title(): string
    {
        return match ($this->event) {
            'assigned' => 'Nouveau courrier à traiter',
            'reassigned' => 'Courrier réaffecté',
            'taken_charge' => 'Prise en charge enregistrée',
            'reply_prepared' => 'Projet de réponse créé',
            'dispatched' => 'Courrier expédié',
            'reminder' => 'Rappel échéance courrier',
            default => 'Notification courrier',
        };
    }
}
