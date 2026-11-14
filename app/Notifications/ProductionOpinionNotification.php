<?php

namespace App\Notifications;

use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmission;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionOpinionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ProductionOccurrence $occurrence,
        public ProductionTransmission $transmission,
        public string $opinion,
        public string $authorName,
        public ?string $comment = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->occurrence->obligation?->label ?? $this->occurrence->reference;
        $opinionLabel = match ($this->opinion) {
            'favorable' => 'favorable',
            'reserve' => 'avec réserve',
            'a_revoir' => 'à revoir',
            default => $this->opinion,
        };

        $mail = (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Productions', 'Avis reçu'))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line(sprintf(
                '%s a donné un avis %s sur « %s » (%s).',
                $this->authorName,
                $opinionLabel,
                $label,
                $this->occurrence->reference
            ));

        if ($this->comment) {
            $mail->line('Commentaire : '.$this->comment);
        }

        return $mail
            ->action('Voir la production', url('/productions/occurrences/'.$this->occurrence->id))
            ->line(NotificationPresentation::FOOTER);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'production.opinion',
            'title' => 'Avis reçu sur une production',
            'message' => sprintf(
                '%s a donné un avis (%s) sur %s.',
                $this->authorName,
                $this->opinion,
                $this->occurrence->reference
            ),
            'opinion' => $this->opinion,
            'occurrence_id' => $this->occurrence->id,
            'transmission_id' => $this->transmission->id,
            'url' => '/productions/occurrences/'.$this->occurrence->id,
        ];
    }
}
