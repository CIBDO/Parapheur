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
        $url = url('/productions/recues?transmission='.$this->transmission->id);
        $label = $this->occurrence->obligation?->label ?? $this->occurrence->reference;

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Productions', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Référence : '.$this->occurrence->reference)
            ->line('Objet : '.$label)
            ->line('Nature : '.($this->transmission->is_rectificative ? 'transmission rectificative' : 'transmission initiale'))
            ->line(NotificationPresentation::actionExpected($this->expectedAction()))
            ->action('Consulter la production', $url)
            ->line('Les pièces demeurent consultables exclusivement dans E-Tresor ; elles ne sont pas jointes à ce message.')
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
            'url' => '/productions/recues?transmission='.$this->transmission->id,
        ];
    }

    private function title(): string
    {
        $label = $this->occurrence->obligation?->label ?? $this->occurrence->reference;

        if ($this->transmission->is_rectificative) {
            return $this->profile === 'delegate'
                ? 'Transmission rectificative (délégation) — '.$label
                : 'Transmission rectificative — '.$label;
        }

        return $this->profile === 'delegate'
            ? 'Transmission au délégué — '.$label
            : 'Transmission à votre attention — '.$label;
    }

    private function body(): string
    {
        $label = $this->occurrence->obligation?->label ?? 'la production';
        $ref = $this->occurrence->reference;
        $rectificative = $this->transmission->is_rectificative;

        if ($this->profile === 'delegate') {
            return $rectificative
                ? sprintf(
                    'Une version rectificative de la production « %s » (%s) vous est adressée en votre qualité de délégué, '
                    .'dans le cadre du circuit de production institutionnelle. Elle se substitue à la communication précédente.',
                    $label,
                    $ref
                )
                : sprintf(
                    'La production « %s » (%s) vous est transmise en votre qualité de délégué, '
                    .'afin que vous en preniez connaissance au nom du titulaire.',
                    $label,
                    $ref
                );
        }

        return $rectificative
            ? sprintf(
                'Une version rectificative de la production « %s » (%s) vous est transmise pour consultation. '
                .'Elle remplace la communication antérieure adressée à votre attention.',
                $label,
                $ref
            )
            : sprintf(
                'La production « %s » (%s) vous a été transmise pour consultation dans le cadre du circuit '
                .'de production institutionnelle de la DGTCP.',
                $label,
                $ref
            );
    }

    private function expectedAction(): string
    {
        return $this->profile === 'delegate'
            ? 'prendre connaissance des pièces dans E-Tresor et assurer le suivi relevant de votre délégation.'
            : 'prendre connaissance des pièces dans E-Tresor et, le cas échéant, enregistrer votre avis.';
    }
}
