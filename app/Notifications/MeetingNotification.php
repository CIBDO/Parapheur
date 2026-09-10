<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Meeting $meeting,
        public string $event,
        public string $message,
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        // Invité externe (AnonymousNotifiable) : e-mail uniquement
        if (! $notifiable instanceof \App\Models\User) {
            return ['mail'];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/parapheur/reunions/'.$this->meeting->id);
        $name = $notifiable->name ?? 'Madame, Monsieur';

        return (new MailMessage)
            ->subject('[e-Parapheur] '.$this->title())
            ->greeting('Bonjour '.$name.',')
            ->line($this->message)
            ->line('Réunion : '.$this->meeting->displayTitle())
            ->line('Référence : '.($this->meeting->reference ?: '#'.$this->meeting->id))
            ->line('Date : '.optional($this->meeting->meeting_date)->format('d/m/Y').' '.$this->meeting->meeting_time)
            ->line('Lieu : '.($this->meeting->location ?: '—'))
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Par : '.$this->actorName))
            ->action('Consulter la réunion', $url)
            ->line('Ceci est une notification automatique de la gestion électronique des réunions DGTCP.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message,
            'event' => $this->event,
            'meeting_id' => $this->meeting->id,
            'reference' => $this->meeting->reference,
            'object' => $this->meeting->displayTitle(),
            'actor' => $this->actorName,
            'url' => '/parapheur/reunions/'.$this->meeting->id,
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'created' => 'Nouvelle réunion',
            'updated' => 'Réunion modifiée',
            'invitation' => 'Nouvelle convocation',
            'place_changed' => 'Changement de lieu',
            'time_changed' => 'Changement d’horaire',
            'postponed' => 'Réunion reportée',
            'cancelled' => 'Réunion annulée',
            'document_added' => 'Document préparatoire ajouté',
            'confirmation_reminder' => 'Rappel de confirmation',
            'meeting_reminder' => 'Rappel de réunion',
            'minutes_available' => 'Compte rendu disponible',
            'decision_assigned' => 'Décision vous concernant',
            'decision_late' => 'Décision en retard',
            'started' => 'Réunion en cours',
            'finished' => 'Réunion terminée',
            default => 'Mise à jour de réunion',
        };
    }
}
