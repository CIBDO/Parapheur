<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
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
        $url = url('/parapheur/agenda/'.$this->appointment->id);
        $name = $notifiable->name ?? 'Madame, Monsieur';

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Agenda', $this->title()))
            ->greeting(NotificationPresentation::greeting((string) $name))
            ->line($this->body())
            ->line('Rendez-vous : '.$this->appointment->displaySubject())
            ->line('Référence : '.($this->appointment->reference ?: '#'.$this->appointment->id))
            ->line('Date : '.(optional($this->appointment->start_at)?->format('d/m/Y H:i') ?: '—'))
            ->line('Lieu : '.($this->appointment->location ?: '—'))
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Intervenant : '.$this->actorName))
            ->action('Consulter le rendez-vous', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
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
            'rejected' => 'Demande de rendez-vous refusée',
            'rescheduled' => 'Rendez-vous reporté',
            'cancelled' => 'Rendez-vous annulé',
            'redirected' => 'Demande réorientée',
            'reminder' => 'Rappel de rendez-vous',
            'document_added' => 'Document ajouté au dossier',
            'followup_created' => 'Suite à donner enregistrée',
            default => 'Mise à jour de l’agenda',
        };
    }

    private function body(): string
    {
        $subject = $this->appointment->displaySubject();
        $date = optional($this->appointment->start_at)?->format('d/m/Y à H:i');
        $location = $this->appointment->location ?: null;
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $detail = trim($this->message);

        return match ($this->event) {
            'created' => sprintf(
                'Une demande de rendez-vous (« %s ») a été enregistrée auprès de l’agenda de la Direction Générale%s.',
                $subject,
                $by
            ),
            'updated' => sprintf(
                'Le rendez-vous « %s » a été modifié%s. Merci de prendre connaissance des nouvelles informations.',
                $subject,
                $by
            ),
            'slot_proposed' => sprintf(
                'Un créneau a été proposé pour le rendez-vous « %s »%s. Merci de le confirmer ou de faire part de vos disponibilités.',
                $subject,
                $by
            ),
            'validated' => sprintf(
                'Le rendez-vous « %s » a été validé%s.%s',
                $subject,
                $by,
                $date ? ' Date prévue : '.$date.'.' : ''
            ),
            'confirmed' => sprintf(
                'Votre rendez-vous avec la Direction Générale (« %s ») est confirmé.%s%s',
                $subject,
                $date ? ' Date : '.$date.'.' : '',
                $location ? ' Lieu : '.$location.'.' : ''
            ),
            'rejected' => sprintf(
                'La demande de rendez-vous « %s » n’a pas été acceptée%s. Vous pouvez consulter le dossier pour plus de précisions.',
                $subject,
                $by
            ),
            'rescheduled' => sprintf(
                'Le rendez-vous « %s » a été reporté%s.%s',
                $subject,
                $by,
                $date ? ' Nouvelle date : '.$date.'.' : ''
            ),
            'cancelled' => sprintf(
                'Le rendez-vous « %s » a été annulé%s.',
                $subject,
                $by
            ),
            'redirected' => sprintf(
                'La demande de rendez-vous « %s » a été réorientée%s. Merci de consulter le dossier pour la suite à donner.',
                $subject,
                $by
            ),
            'reminder' => $this->reminderBody($subject, $detail, $date),
            'document_added' => sprintf(
                'Un document a été ajouté au dossier du rendez-vous « %s »%s.',
                $subject,
                $by
            ),
            'followup_created' => sprintf(
                'Une suite à donner a été enregistrée pour le rendez-vous « %s »%s.',
                $subject,
                $by
            ),
            default => $detail !== ''
                ? $detail
                : sprintf('Une mise à jour concerne le rendez-vous « %s ».', $subject),
        };
    }

    private function reminderBody(string $subject, string $detail, ?string $date): string
    {
        if (preg_match('/J-1/iu', $detail)) {
            return sprintf(
                'Rappel : le rendez-vous « %s » est prévu demain%s. Merci de vous organiser en conséquence.',
                $subject,
                $date ? ' ('.$date.')' : ''
            );
        }

        if (preg_match('/H-2/iu', $detail)) {
            return sprintf(
                'Rappel : le rendez-vous « %s » aura lieu dans environ deux heures%s.',
                $subject,
                $date ? ' ('.$date.')' : ''
            );
        }

        if (preg_match('/H-30|30 minutes/iu', $detail)) {
            return sprintf(
                'Rappel : le rendez-vous « %s » aura lieu dans environ trente minutes%s.',
                $subject,
                $date ? ' ('.$date.')' : ''
            );
        }

        return sprintf(
            'Rappel concernant le rendez-vous « %s »%s.',
            $subject,
            $date ? ', prévu le '.$date : ''
        );
    }
}
