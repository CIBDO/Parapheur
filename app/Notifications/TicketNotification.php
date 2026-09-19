<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $event,
        public string $message,
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        $pref = \App\Models\TicketNotificationPreference::query()
            ->where('user_id', $notifiable->id)
            ->first();

        if ($pref && is_array($pref->muted_events) && in_array($this->event, $pref->muted_events, true)) {
            return [];
        }

        $channels = [];
        if ($pref === null || $pref->database_enabled) {
            $channels[] = 'database';
        }
        if ($pref === null || $pref->mail_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url($this->frontendPath());

        return (new MailMessage)
            ->subject('[Ticketing] '.$this->title())
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message)
            ->line('Numéro : '.$this->ticket->number)
            ->line('Titre : '.$this->ticket->title)
            ->line('Statut : '.($this->ticket->status?->label() ?? (string) $this->ticket->status?->value))
            ->action('Ouvrir le ticket', $url)
            ->line('Notification automatique — Bureau Numérique DGTCP.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message,
            'event' => $this->event,
            'ticket_id' => $this->ticket->id,
            'number' => $this->ticket->number,
            'ticket_title' => $this->ticket->title,
            'status' => $this->ticket->status?->value,
            'actor' => $this->actorName,
            'url' => $this->frontendPath(),
        ];
    }

    private function frontendPath(): string
    {
        return '/ticketing/'.$this->ticket->id;
    }

    private function title(): string
    {
        return match ($this->event) {
            'created' => 'Nouveau ticket',
            'assigned' => 'Ticket affecté',
            'taken_charge' => 'Prise en charge',
            'comment_requester', 'comment_agent', 'requester_replied' => 'Nouveau commentaire',
            'waiting_requester' => 'En attente de votre réponse',
            'escalated' => 'Ticket escaladé',
            'sla_warning' => 'Alerte SLA',
            'sla_breach' => 'Dépassement SLA',
            'resolved' => 'Ticket résolu',
            'reopened' => 'Ticket réouvert',
            'closed' => 'Ticket clôturé',
            'priority_changed' => 'Priorité modifiée',
            default => 'Notification ticketing',
        };
    }
}
