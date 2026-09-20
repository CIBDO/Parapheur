<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Meeting $meeting,
        public string $event,
        public string $message = '',
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
        $url = url('/parapheur/reunions/'.$this->meeting->id);
        $name = $notifiable->name ?? 'Madame, Monsieur';

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Réunions', $this->title()))
            ->greeting(NotificationPresentation::greeting((string) $name))
            ->line($this->body())
            ->line('Réunion : '.$this->meeting->displayTitle())
            ->line('Référence : '.($this->meeting->reference ?: '#'.$this->meeting->id))
            ->line('Date : '.optional($this->meeting->meeting_date)->format('d/m/Y').' '.$this->meeting->meeting_time)
            ->line('Lieu : '.($this->meeting->location ?: '—'))
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Intervenant : '.$this->actorName))
            ->action('Consulter la réunion', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
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
            'invitation' => 'Convocation à une réunion',
            'place_changed' => 'Changement de lieu',
            'time_changed' => 'Changement d’horaire',
            'postponed' => 'Réunion reportée',
            'cancelled' => 'Réunion annulée',
            'document_added' => 'Document préparatoire ajouté',
            'confirmation_reminder' => 'Rappel de confirmation de présence',
            'meeting_reminder' => 'Rappel de réunion',
            'minutes_available' => 'Compte rendu disponible',
            'decision_assigned' => 'Décision vous concernant',
            'decision_late' => 'Décision en retard',
            'started' => 'Réunion en cours',
            'finished' => 'Réunion terminée',
            default => 'Mise à jour de réunion',
        };
    }

    private function body(): string
    {
        $title = $this->meeting->displayTitle();
        $date = optional($this->meeting->meeting_date)->format('d/m/Y');
        $time = trim((string) $this->meeting->meeting_time);
        $when = trim(($date ?: '').($time !== '' ? ' à '.$time : ''));
        $location = $this->meeting->location ?: null;
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $detail = $this->decisionDetail();

        return match ($this->event) {
            'created' => sprintf(
                'Une nouvelle réunion « %s » a été créée%s.%s',
                $title,
                $by,
                $when !== '' ? ' Date prévue : '.$when.'.' : ''
            ),
            'updated' => sprintf(
                'La réunion « %s » a été mise à jour%s. Merci de consulter les informations actualisées.',
                $title,
                $by
            ),
            'invitation' => sprintf(
                'Vous êtes convoqué(e) à la réunion « %s »%s.%s%s Merci de confirmer votre participation.',
                $title,
                $by,
                $when !== '' ? ' Date : '.$when.'.' : '',
                $location ? ' Lieu : '.$location.'.' : ''
            ),
            'place_changed' => sprintf(
                'Le lieu de la réunion « %s » a été modifié%s.%s',
                $title,
                $by,
                $location ? ' Nouveau lieu : '.$location.'.' : ''
            ),
            'time_changed' => sprintf(
                'L’horaire de la réunion « %s » a été modifié%s.%s',
                $title,
                $by,
                $when !== '' ? ' Nouvelle date : '.$when.'.' : ''
            ),
            'postponed' => sprintf(
                'La réunion « %s » a été reportée%s.%s',
                $title,
                $by,
                $when !== '' ? ' Nouvelle date : '.$when.'.' : ''
            ),
            'cancelled' => sprintf(
                'La réunion « %s » a été annulée%s.',
                $title,
                $by
            ),
            'document_added' => sprintf(
                'Un document préparatoire a été ajouté à la réunion « %s »%s. Vous pouvez le consulter dans le dossier.',
                $title,
                $by
            ),
            'confirmation_reminder' => sprintf(
                'Rappel : merci de confirmer votre participation à la réunion « %s »%s.',
                $title,
                $when !== '' ? ', prévue le '.$when : ''
            ),
            'meeting_reminder' => sprintf(
                'Rappel : la réunion « %s » aura lieu prochainement%s%s.',
                $title,
                $when !== '' ? ' ('.$when.')' : '',
                $location ? ' — '.$location : ''
            ),
            'minutes_available' => sprintf(
                'Le compte rendu de la réunion « %s » est désormais disponible. Merci d’en prendre connaissance.',
                $title
            ),
            'decision_assigned' => sprintf(
                'Une décision issue de la réunion « %s » vous a été confiée%s.%s Merci d’en assurer le suivi.',
                $title,
                $by,
                $detail !== '' ? ' Objet : '.$detail.'.' : ''
            ),
            'decision_late' => sprintf(
                'La décision qui vous a été confiée dans le cadre de la réunion « %s » est en retard%s. Merci d’actualiser le suivi sans délai.',
                $title,
                $detail !== '' ? ' (« '.$detail.' »)' : ''
            ),
            'started' => sprintf(
                'La réunion « %s » est désormais en cours.',
                $title
            ),
            'finished' => sprintf(
                'La réunion « %s » est terminée. Les suites éventuelles seront communiquées via le Bureau Numérique.',
                $title
            ),
            default => trim($this->message) !== ''
                ? $this->message
                : sprintf('Une mise à jour concerne la réunion « %s ».', $title),
        };
    }

    private function decisionDetail(): string
    {
        $message = trim($this->message);
        if ($message === '') {
            return '';
        }

        if (preg_match('/décision[^«]*«\s*(.+?)\s*»/iu', $message, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/affectée\s*:\s*(.+)$/iu', $message, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/Échéance proche pour la décision «\s*(.+?)\s*»/iu', $message, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
