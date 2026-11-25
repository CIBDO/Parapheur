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
        public string $profile = 'participant',
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
        $date = optional($this->meeting->meeting_date)->format('d/m/Y');
        $time = trim((string) $this->meeting->meeting_time);
        $when = trim(($date ?: '—').($time !== '' ? ' à '.$time : ''));

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Réunions', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Réunion : '.$this->meeting->displayTitle())
            ->line('Référence : '.($this->meeting->reference ?: '#'.$this->meeting->id))
            ->line('Date et heure : '.$when)
            ->line('Lieu : '.($this->meeting->location ?: '—'))
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Intervenant : '.$this->actorName))
            ->line(NotificationPresentation::actionExpected($this->expectedAction()))
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
            'profile' => $this->profile,
            'url' => '/parapheur/reunions/'.$this->meeting->id,
        ];
    }

    private function title(): string
    {
        $short = $this->meeting->displayTitle();

        return match ($this->event) {
            'created' => 'Création de réunion — '.$short,
            'updated' => 'Actualisation de réunion — '.$short,
            'invitation' => 'Convocation — '.$short,
            'place_changed' => 'Changement de lieu — '.$short,
            'time_changed' => 'Changement d’horaire — '.$short,
            'postponed' => 'Report de réunion — '.$short,
            'cancelled' => 'Annulation de réunion — '.$short,
            'document_added' => 'Document préparatoire — '.$short,
            'confirmation_reminder' => 'Rappel de confirmation de présence — '.$short,
            'meeting_reminder' => 'Rappel de tenue de réunion — '.$short,
            'minutes_available' => 'Compte rendu disponible — '.$short,
            'decision_assigned' => 'Décision à suivre — '.$short,
            'decision_late' => 'Décision en retard — '.$short,
            'started' => 'Réunion en cours — '.$short,
            'finished' => 'Clôture de réunion — '.$short,
            default => 'Communication relative à une réunion — '.$short,
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
            'invitation' => match ($this->profile) {
                'chair' => sprintf(
                    'Vous êtes convoqué(e) à présider la réunion « %s »%s.%s%s Merci de confirmer votre participation.',
                    $title,
                    $by,
                    $when !== '' ? ' Date : '.$when.'.' : '',
                    $location ? ' Lieu : '.$location.'.' : ''
                ),
                'secretary' => sprintf(
                    'Vous êtes désigné(e) pour le secrétariat de la réunion « %s »%s.%s%s Merci de confirmer votre participation.',
                    $title,
                    $by,
                    $when !== '' ? ' Date : '.$when.'.' : '',
                    $location ? ' Lieu : '.$location.'.' : ''
                ),
                default => sprintf(
                    'Vous êtes convoqué(e), en qualité de participant, à la réunion « %s »%s.%s%s Merci de confirmer votre participation.',
                    $title,
                    $by,
                    $when !== '' ? ' Date : '.$when.'.' : '',
                    $location ? ' Lieu : '.$location.'.' : ''
                ),
            },
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
                'Dans le cadre de l’organisation institutionnelle des travaux, votre confirmation de participation '
                .'à la réunion « %s »%s demeure attendue.',
                $title,
                $when !== '' ? ', prévue le '.$when : ''
            ),
            'meeting_reminder' => sprintf(
                'Nous vous rappelons la tenue prochaine de la réunion « %s »%s%s. '
                .'Merci de vous organiser afin d’y participer selon votre qualité.',
                $title,
                $when !== '' ? ' ('.$when.')' : '',
                $location ? ', au lieu suivant : '.$location : ''
            ),
            'minutes_available' => sprintf(
                'Le compte rendu de la réunion « %s » est désormais disponible dans E-Tresor. '
                .'Vous êtes invité(e) à en prendre connaissance.',
                $title
            ),
            'decision_assigned' => sprintf(
                'Une décision issue de la réunion « %s » vous a été confiée%s.%s',
                $title,
                $by,
                $detail !== '' ? ' Objet : '.$detail.'.' : ''
            ),
            'decision_late' => sprintf(
                'La décision qui vous a été confiée au titre de la réunion « %s » n’a pas été soldée dans le délai imparti%s.',
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

    private function expectedAction(): string
    {
        return match ($this->event) {
            'invitation', 'confirmation_reminder' => 'confirmer votre participation dans E-Tresor.',
            'meeting_reminder' => 'prendre vos dispositions pour assister à la réunion à la date et au lieu indiqués.',
            'place_changed', 'time_changed', 'postponed', 'updated' => 'prendre connaissance des nouvelles modalités.',
            'cancelled' => 'prendre acte de l’annulation ; aucune présence n’est requise.',
            'document_added', 'minutes_available' => 'consulter les documents mis à disposition dans le dossier de réunion.',
            'decision_assigned' => 'assurer le suivi de la décision et en rendre compte à l’échéance.',
            'decision_late' => 'actualiser sans délai le suivi de la décision dans E-Tresor.',
            default => 'consulter le dossier de réunion dans E-Tresor.',
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
