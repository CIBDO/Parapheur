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

        $mail = (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Productions', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Référence : '.$this->occurrence->reference)
            ->line('Objet : '.$label)
            ->line('Avis formulé : '.$this->opinionLabel());

        if ($this->comment) {
            $mail->line('Commentaire : « '.$this->comment.' »');
        }

        return $mail
            ->line(NotificationPresentation::actionExpected(
                'prendre connaissance de cet avis et assurer, le cas échéant, les suites utiles '
                .'(complément, correction ou nouvelle transmission).'
            ))
            ->action('Voir la production', url('/productions/occurrences/'.$this->occurrence->id))
            ->line(NotificationPresentation::FOOTER);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'production.opinion',
            'title' => $this->title(),
            'message' => $this->body(),
            'opinion' => $this->opinion,
            'occurrence_id' => $this->occurrence->id,
            'transmission_id' => $this->transmission->id,
            'url' => '/productions/occurrences/'.$this->occurrence->id,
        ];
    }

    private function title(): string
    {
        return sprintf(
            'Avis %s reçu — %s',
            $this->opinionLabel(),
            $this->occurrence->reference
        );
    }

    private function body(): string
    {
        $label = $this->occurrence->obligation?->label ?? $this->occurrence->reference;

        return sprintf(
            '%s a formulé un avis %s sur la production « %s » (%s), '
            .'dans le cadre de la consultation des destinataires.',
            $this->authorName,
            $this->opinionLabel(),
            $label,
            $this->occurrence->reference
        );
    }

    private function opinionLabel(): string
    {
        return match ($this->opinion) {
            'favorable' => 'favorable',
            'reserve' => 'avec réserve',
            'a_revoir' => 'à revoir',
            default => $this->opinion,
        };
    }
}
