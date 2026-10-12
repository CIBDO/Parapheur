<?php

namespace App\Notifications;

use App\Models\ProductionOccurrence;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'producer'|'deputy'|'validator'|'hierarchy'|'recipient'  $profile
     */
    public function __construct(
        public ProductionOccurrence $occurrence,
        public string $kind = 'reminder',
        public bool $escalate = false,
        public string $profile = 'producer',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/productions/occurrences/'.$this->occurrence->id);

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Productions', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Référence : '.$this->occurrence->reference)
            ->line('Échéance : '.($this->occurrence->deadline_at?->timezone('Africa/Bamako')->format('d/m/Y H:i') ?? '—'))
            ->action('Ouvrir la production', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->escalate ? 'production.escalated' : 'production.reminder',
            'title' => $this->title(),
            'message' => $this->body(),
            'event' => $this->escalate ? 'escalated' : 'reminder',
            'profile' => $this->profile,
            'occurrence_id' => $this->occurrence->id,
            'url' => '/productions/occurrences/'.$this->occurrence->id,
        ];
    }

    private function title(): string
    {
        if ($this->escalate) {
            return match ($this->profile) {
                'hierarchy' => 'Escalade hiérarchique — production en retard',
                'recipient' => 'Production attendue en retard',
                default => 'Production en retard',
            };
        }

        return match ($this->profile) {
            'validator' => 'Production à valider',
            'hierarchy' => 'Suivi hiérarchique d’une production',
            'recipient' => 'Production attendue à votre attention',
            'deputy' => 'Rappel au suppléant',
            default => 'Rappel de production',
        };
    }

    private function body(): string
    {
        $label = $this->occurrence->obligation?->label ?? 'cette production';
        $ref = $this->occurrence->reference;

        if ($this->escalate) {
            return match ($this->profile) {
                'hierarchy' => sprintf('La production « %s » (%s) de votre structure n’a pas été transmise à l’échéance. Cette escalade est portée à votre niveau pour suivi.', $label, $ref),
                'recipient' => sprintf('La production « %s » (%s), attendue à votre attention, n’a pas encore été transmise.', $label, $ref),
                default => sprintf('La production « %s » (%s), dont vous avez la charge, a dépassé son échéance. Merci de la transmettre ou de justifier le retard.', $label, $ref),
            };
        }

        return match ($this->profile) {
            'validator' => sprintf('La production « %s » (%s) attend votre validation. Merci de statuer avant l’échéance.', $label, $ref),
            'hierarchy' => sprintf('Pour votre information : la production « %s » (%s) de votre structure approche de son échéance.', $label, $ref),
            'recipient' => sprintf('La production « %s » (%s) est attendue à votre attention. Elle vous sera transmise dès qu’elle sera prête.', $label, $ref),
            'deputy' => sprintf('En votre qualité de suppléant, rappel concernant la production « %s » (%s). Merci de vous assurer de son dépôt si le titulaire est absent.', $label, $ref),
            default => sprintf('Rappel : la production « %s » (%s), dont vous êtes responsable, approche de son échéance. Merci d’en assurer le dépôt et la transmission.', $label, $ref),
        };
    }
}
