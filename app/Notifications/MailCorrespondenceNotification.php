<?php

namespace App\Notifications;

use App\Models\Correspondence;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MailCorrespondenceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Correspondence $correspondence,
        public string $event,
        public string $message = '',
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url($this->frontendPath());

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Courrier', $this->title()))
            ->greeting(NotificationPresentation::greeting((string) ($notifiable->name ?? '')))
            ->line($this->body())
            ->line('Référence : '.$this->reference())
            ->line('Objet : '.$this->correspondence->subject)
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Intervenant : '.$this->actorName))
            ->action('Ouvrir le courrier', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
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

    private function reference(): string
    {
        return $this->correspondence->arrival_number
            ?: $this->correspondence->departure_number
            ?: '#'.$this->correspondence->id;
    }

    private function title(): string
    {
        return match ($this->event) {
            'assigned' => 'Nouveau courrier à traiter',
            'reassigned' => 'Courrier réaffecté',
            'taken_charge' => 'Prise en charge enregistrée',
            'reply_prepared' => 'Projet de réponse prêt',
            'dispatched' => 'Courrier expédié',
            'reminder' => 'Rappel d’échéance courrier',
            'complement_requested' => 'Complément d’information demandé',
            default => 'Mise à jour courrier',
        };
    }

    private function body(): string
    {
        $ref = $this->reference();
        $subject = (string) $this->correspondence->subject;
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $detail = trim($this->message);

        return match ($this->event) {
            'assigned' => sprintf(
                'Le courrier %s (« %s ») vous a été confié%s. Merci d’en assurer le traitement dans les délais impartis.',
                $ref,
                $subject,
                $by
            ),
            'reassigned' => sprintf(
                'Le courrier %s (« %s ») a été réaffecté à votre attention%s.',
                $ref,
                $subject,
                $by
            ),
            'taken_charge' => sprintf(
                'La prise en charge du courrier %s (« %s ») a été enregistrée%s. Le traitement est désormais en cours.',
                $ref,
                $subject,
                $by
            ),
            'reply_prepared' => sprintf(
                'Un projet de réponse a été établi pour le courrier %s (« %s »). Vous pouvez le consulter et le valider.',
                $ref,
                $subject
            ),
            'dispatched' => sprintf(
                'Le courrier %s (« %s ») a été expédié. L’opération est enregistrée au Bureau Numérique.',
                $ref,
                $subject
            ),
            'complement_requested' => sprintf(
                'Un complément d’information a été demandé concernant le courrier %s (« %s »)%s. Merci d’y donner suite.',
                $ref,
                $subject,
                $by
            ),
            'reminder' => sprintf(
                'Rappel concernant le courrier %s (« %s »).%s',
                $ref,
                $subject,
                $this->reminderSuffix($detail)
            ),
            default => $detail !== ''
                ? $detail
                : sprintf('Une mise à jour concerne le courrier %s (« %s »).', $ref, $subject),
        };
    }

    private function reminderSuffix(string $detail): string
    {
        if ($detail === '') {
            return ' Merci de vérifier l’état d’avancement du dossier.';
        }

        // Reformuler les rappels techniques en ton institutionnel
        if (preg_match('/en retard de (\d+)/iu', $detail, $m)) {
            $due = '';
            if (preg_match('/Échéance\s*:\s*([0-9\/]+)/iu', $detail, $d)) {
                $due = ' (échéance du '.$d[1].')';
            }

            return sprintf(
                ' Ce dossier est en retard de %d jour(s)%s. Une action rapide est attendue.',
                (int) $m[1],
                $due
            );
        }

        if (preg_match('/traité aujourd.?hui/iu', $detail)) {
            return ' Ce dossier doit être traité aujourd’hui.';
        }

        if (preg_match('/dans (\d+) jour/iu', $detail, $m)) {
            return sprintf(' Ce dossier doit être traité dans %d jour(s).', (int) $m[1]);
        }

        return ' '.$detail;
    }
}
