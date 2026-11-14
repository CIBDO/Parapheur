<?php

namespace App\Notifications;

use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ProductionMorningDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, object{reference: string, label: string, id: int}>  $items
     */
    public function __construct(
        public Collection $items,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Productions', 'Rappel — éléments à envoyer'))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line('Bonjour, certains éléments attendus aujourd’hui n’ont pas encore été envoyés. Merci de les transmettre si ce n’est pas déjà fait :');

        foreach ($this->items->take(15) as $item) {
            $mail->line(sprintf('• %s (%s)', $item->label, $item->reference));
        }

        if ($this->items->count() > 15) {
            $mail->line(sprintf('… et %d autre(s).', $this->items->count() - 15));
        }

        return $mail
            ->action('Ouvrir Aujourd’hui', url('/productions?view=today'))
            ->line(NotificationPresentation::FOOTER);
    }
}
