<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
        public string $event,
        public string $message,
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof \App\Models\User) {
            return ['mail'];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/parapheur/agenda/'.$this->appointment->id);
        $name = $notifiable->name ?? 'Madame, Monsieur';

        return (new MailMessage)
            ->subject('[E-Tresor] '.$this->title())
            ->greeting('Bonjour '.$name.',')
            ->line($this->message)
            ->line('Rendez-vous : '.$this->appointment->displaySubject())
            ->line('Référence : '.($this->appointment->reference ?: '#'.$this->appointment->id))
            ->line('Date : '.(optional($this->appointment->start_at)?->format('d/m/Y H:i') ?: '—'))
            ->line('Lieu : '.($this->appointment->location ?: '—'))
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Par : '.$this->actorName))
            ->action('Consulter le rendez-vous', $url)
            ->line('Ceci est une notification automatique de l’agenda du Directeur Général DGTCP.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message,
            'event' => $this->event,
            'appointment_id' => $this->appointment->id,
            'reference' => $this->appointment->reference,
            'object' => $this->appointment->displaySubject(),
            'actor' => $this->actorName,
            'url' => '/parapheur/agenda/'.$this->appointment->id,
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'created' => 'Nouvelle demande de rendez-vous',
            'updated' => 'Rendez-vous modifié',
            'slot_proposed' => 'Créneau proposé',
            'validated' => 'Rendez-vous validé',
            'confirmed' => 'Rendez-vous confirmé',
            'rejected' => 'Demande refusée',
            'rescheduled' => 'Rendez-vous reporté',
            'cancelled' => 'Rendez-vous annulé',
            'redirected' => 'Demande réorientée',
            'reminder' => 'Rappel de rendez-vous',
            'document_added' => 'Document ajouté',
            'followup_created' => 'Suite à donner',
            default => 'Mise à jour de rendez-vous',
        };
    }
}