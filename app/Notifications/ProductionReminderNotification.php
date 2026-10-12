<?php

namespace App\Notifications;

use App\Models\ProductionOccurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ProductionOccurrence $occurrence,
        public string $kind = 'reminder',
        public bool $escalate = false,
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
            ->subject(($this->escalate ? 'Escalade' : 'Rappel').' production : '.$label)
            ->line($this->escalate
                ? 'Une production est en retard et a été escaladée.'
                : 'Rappel concernant une production attendue.')
            ->line('Référence : '.$this->occurrence->reference)
            ->line('Échéance : '.($this->occurrence->deadline_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—'))
            ->action('Ouvrir dans E-Trésor', $url);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->escalate ? 'production.escalated' : 'production.reminder',
            'title' => $this->escalate ? 'Escalade production' : 'Rappel production',
            'message' => ($this->occurrence->obligation?->label ?? 'Production').' — '.$this->occurrence->reference,
            'occurrence_id' => $this->occurrence->id,
            'url' => '/productions/occurrences/'.$this->occurrence->id,
        ];
    }
}
