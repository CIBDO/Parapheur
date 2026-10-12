<?php

namespace App\Notifications;

use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionTransmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ProductionOccurrence $occurrence,
        public ProductionTransmission $transmission,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->occurrence->obligation?->label ?? $this->occurrence->reference;
        $url = url('/productions/occurrences/'.$this->occurrence->id);

        return (new MailMessage)
            ->subject('Production transmise : '.$label)
            ->line('Une production vous a été transmise dans E-Trésor.')
            ->line('Référence : '.$this->occurrence->reference)
            ->line($this->transmission->is_rectificative ? 'Il s\'agit d\'une transmission rectificative.' : '')
            ->action('Consulter dans E-Trésor', $url)
            ->line('Les documents confidentiels ne sont accessibles que dans l\'application.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'production.transmitted',
            'title' => 'Production transmise',
            'message' => ($this->occurrence->obligation?->label ?? 'Production').' — '.$this->occurrence->reference,
            'occurrence_id' => $this->occurrence->id,
            'transmission_id' => $this->transmission->id,
            'url' => '/productions/occurrences/'.$this->occurrence->id,
        ];
    }
}
