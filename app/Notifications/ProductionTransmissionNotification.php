<?php

namespace App\Notifications;

use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmission;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionTransmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'recipient'|'delegate'  $profile
     */
    public function __construct(
        public ProductionOccurrence $occurrence,
        public ProductionTransmission $transmission,
        public string $profile = 'recipient',
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
            ->line('Objet : '.($this->occurrence->obligation?->label ?? $this->occurrence->reference))
            ->action('Consulter la production', $url)
            ->line('Les pièces restent consultables dans E-Tresor. Elles ne sont pas jointes à ce message.')
            ->line(NotificationPresentation::FOOTER);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'production.transmitted',
            'title' => $this->title(),
            'message' => $this->body(),
            'event' => $this->transmission->is_rectificative ? 'rectificative' : 'transmitted',
            'profile' => $this->profile,
            'occurrence_id' => $this->occurrence->id,
            'transmission_id' => $this->transmission->id,
            'url' => '/productions/occurrences/'.$this->occurrence->id,
        ];
    }

    private function title(): string
    {
        if ($this->transmission->is_rectificative) {
            return $this->profile === 'delegate'
                ? 'Transmission rectificative — délégation'
                : 'Transmission rectificative';
        }

        return $this->profile === 'delegate'
            ? 'Production transmise au délégué'
            : 'Production transmise à votre attention';
    }

    private function body(): string
    {
        $label = $this->occurrence->obligation?->label ?? 'la production';
        $ref = $this->occurrence->reference;
        $rectificative = $this->transmission->is_rectificative;

        if ($this->profile === 'delegate') {
            return $rectificative
                ? sprintf('Une version rectificative de « %s » (%s) vous est transmise en votre qualité de délégué. Merci d’en prendre connaissance.', $label, $ref)
                : sprintf('La production « %s » (%s) vous est transmise en votre qualité de délégué. Merci d’en prendre connaissance à la place du titulaire.', $label, $ref);
        }

        return $rectificative
            ? sprintf('Une version rectificative de « %s » (%s) vous est transmise pour consultation. Elle remplace la communication précédente.', $label, $ref)
            : sprintf('La production « %s » (%s) vous est transmise pour consultation, à votre attention. Merci d’en prendre connaissance.', $label, $ref);
    }
}
