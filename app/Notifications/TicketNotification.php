<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $event,
        public string $message = '',
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
        $status = $this->ticket->status?->label() ?? (string) ($this->ticket->status?->value ?? '—');

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Centre de services', $this->title()))
            ->greeting(NotificationPresentation::greeting((string) ($notifiable->name ?? '')))
            ->line($this->body())
            ->line('Référence : '.$this->ticket->number)
            ->line('Objet : '.$this->ticket->title)
            ->line('Statut : '.$status)
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Intervenant : '.$this->actorName))
            ->action('Consulter le ticket', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
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
            'created' => 'Demande enregistrée',
            'assigned' => 'Ticket confié à votre attention',
            'transferred' => 'Ticket transféré',
            'taken_charge' => 'Prise en charge confirmée',
            'comment_requester', 'requester_replied' => 'Message du demandeur',
            'comment_agent' => 'Réponse du Centre de services',
            'waiting_requester' => 'Votre réponse est attendue',
            'escalated' => 'Escalade du ticket',
            'sla_warning' => 'Alerte délai de service (SLA)',
            'sla_breach' => 'Dépassement du délai de service (SLA)',
            'ola_warning' => 'Alerte engagement interne (OLA)',
            'ola_breach' => 'Dépassement de l’engagement interne (OLA)',
            'resolved' => 'Solution proposée — validation requise',
            'solution_accepted' => 'Solution acceptée',
            'reopened' => 'Ticket réouvert',
            'closed' => 'Ticket clôturé',
            'priority_changed' => 'Priorité actualisée',
            'approval_accepted' => 'Demande approuvée',
            'approval_refused' => 'Demande non approuvée',
            default => 'Mise à jour du Centre de services',
        };
    }

    private function body(): string
    {
        $number = $this->ticket->number;
        $object = $this->ticket->title;
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $detail = $this->detail();

        return match ($this->event) {
            'created' => sprintf(
                'La demande %s (« %s ») a été enregistrée auprès du Centre de services. Vous serez tenu informé de la suite du traitement.',
                $number,
                $object
            ),
            'assigned' => sprintf(
                'Le ticket %s vous a été confié%s. Merci d’en assurer le traitement dans les meilleurs délais.',
                $number,
                $by
            ),
            'transferred' => sprintf(
                'Le ticket %s a été transféré vers votre périmètre%s.%s',
                $number,
                $by,
                $detail !== '' ? ' '.$detail : ''
            ),
            'taken_charge' => sprintf(
                'Le ticket %s (« %s ») a été pris en charge%s. Le traitement est désormais en cours.',
                $number,
                $object,
                $by
            ),
            'comment_requester', 'requester_replied' => sprintf(
                'Un nouveau message du demandeur a été publié sur le ticket %s. Merci d’en prendre connaissance.',
                $number
            ),
            'comment_agent' => sprintf(
                'Le Centre de services a publié une réponse sur votre ticket %s. Nous vous invitons à la consulter.',
                $number
            ),
            'waiting_requester' => sprintf(
                'Le traitement du ticket %s est temporairement en attente de votre retour. Merci de répondre dès que possible afin de poursuivre le dossier.',
                $number
            ),
            'escalated' => sprintf(
                'Le ticket %s a fait l’objet d’une escalade%s.%s',
                $number,
                $by,
                $detail !== '' ? ' Motif : '.$detail : ''
            ),
            'sla_warning' => sprintf(
                'Le délai de service (SLA) du ticket %s approche de son échéance%s. Une action rapide est recommandée.',
                $number,
                $detail !== '' ? ' ('.$detail.')' : ''
            ),
            'sla_breach' => sprintf(
                'Le délai de service (SLA) du ticket %s a été dépassé%s. Une intervention immédiate est requise.',
                $number,
                $detail !== '' ? ' — '.$detail : ''
            ),
            'ola_warning' => sprintf(
                'L’engagement interne (OLA) du ticket %s approche de son échéance%s. Merci d’accélérer le traitement.',
                $number,
                $detail !== '' ? ' ('.$detail.')' : ''
            ),
            'ola_breach' => sprintf(
                'L’engagement interne (OLA) du ticket %s a été dépassé%s. Une action corrective est attendue.',
                $number,
                $detail !== '' ? ' — '.$detail : ''
            ),
            'resolved' => sprintf(
                'Une solution a été proposée pour le ticket %s%s. Merci de la valider ou d’indiquer les réserves éventuelles.',
                $number,
                $by
            ),
            'solution_accepted' => sprintf(
                'La solution proposée pour le ticket %s a été acceptée. Nous vous remercions de votre collaboration.',
                $number
            ),
            'reopened' => sprintf(
                'Le ticket %s a été réouvert%s.%s',
                $number,
                $by,
                $detail !== '' ? ' Motif : '.$detail : ''
            ),
            'closed' => sprintf(
                'Le ticket %s a été clôturé%s. Ce dossier est désormais archivé dans le Centre de services.',
                $number,
                $by
            ),
            'priority_changed' => sprintf(
                'La priorité du ticket %s a été actualisée%s.%s',
                $number,
                $by,
                $detail !== '' ? ' '.$detail : ''
            ),
            'approval_accepted' => sprintf(
                'Votre demande %s a été approuvée%s. Le traitement peut désormais être engagé.',
                $number,
                $by
            ),
            'approval_refused' => sprintf(
                'Votre demande %s n’a pas été approuvée%s.%s',
                $number,
                $by,
                $detail !== '' ? ' Motif : '.$detail : ''
            ),
            default => $this->message !== ''
                ? $this->message
                : sprintf('Une mise à jour concerne le ticket %s (« %s »).', $number, $object),
        };
    }

    /**
     * Extrait un détail utile (motif, pourcentage…) depuis le message passé par les services.
     */
    private function detail(): string
    {
        $message = trim($this->message);
        if ($message === '') {
            return '';
        }

        $prefixes = [
            'Ticket créé : ',
            'Ticket affecté.',
            'Ticket pris en charge.',
            'Ticket transféré.',
            'Ticket escaladé.',
            'Escalade : ',
            'Ticket résolu — validation demandée.',
            'Ticket réouvert : ',
            'Ticket clôturé.',
            'Ticket clôturé automatiquement.',
            'En attente de votre réponse.',
            'Nouveau commentaire du demandeur.',
            'Le demandeur a répondu.',
            'Nouveau commentaire sur votre ticket.',
            'Note interne ajoutée.',
            'Demande approuvée — traitement autorisé.',
            'Demande refusée : ',
            'Solution acceptée — merci de noter votre satisfaction.',
            'SLA de première réponse dépassé.',
            'SLA de résolution dépassé.',
            'OLA de première réponse dépassé.',
            'OLA de résolution dépassé.',
            'SLA proche du dépassement ',
            'OLA proche du dépassement ',
            'Priorité modifiée en ',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($message, $prefix)) {
                $message = trim(substr($message, strlen($prefix)), " \t\n\r\0\x0B:.—-");
                break;
            }
        }

        // Pourcentages du type "(85%)."
        if (preg_match('/^\((\d+%)\)\.?$/', $message, $m)) {
            return $m[1];
        }

        return $message;
    }
}
