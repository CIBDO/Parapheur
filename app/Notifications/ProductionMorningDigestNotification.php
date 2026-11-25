<?php

namespace App\Notifications;

use App\Support\NotificationPresentation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ProductionMorningDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, object{reference: string, label: string, id: int, deadline_at?: mixed}>  $items
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
        $today = Carbon::now('Africa/Bamako')->format('d/m/Y');
        $count = $this->items->count();

        $mail = (new MailMessage)
            ->subject(NotificationPresentation::mailSubject(
                'Productions',
                'Productions du jour à transmettre — '.$today
            ))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line(
                'Au titre des obligations de production quotidienne de la Direction Générale '
                .'du Trésor et de la Comptabilité Publique, '
                .($count > 1
                    ? $count.' éléments attendus ce jour n’ont pas encore été transmis dans E-Tresor.'
                    : 'un élément attendu ce jour n’a pas encore été transmis dans E-Tresor.')
            )
            ->line('Détail des livrables concernés :');

        foreach ($this->items->take(15) as $index => $item) {
            $deadline = $this->formatDeadline($item->deadline_at ?? null);
            $mail->line(sprintf(
                '%d. %s — Référence : %s — Échéance : %s',
                $index + 1,
                $item->label,
                $item->reference,
                $deadline
            ));
        }

        if ($count > 15) {
            $mail->line(sprintf('… ainsi que %d autre(s) élément(s).', $count - 15));
        }

        return $mail
            ->line(NotificationPresentation::actionExpected(
                'procéder au dépôt et à la transmission de ces livrables avant l’échéance indiquée, '
                .'ou signaler toute difficulté dans le dossier concerné.'
            ))
            ->action('Accéder aux productions du jour', url('/productions?view=today'))
            ->line(NotificationPresentation::FOOTER);
    }

    private function formatDeadline(mixed $deadline): string
    {
        if ($deadline instanceof \Carbon\CarbonInterface) {
            return NotificationPresentation::formatDateTime($deadline);
        }

        if (is_string($deadline) && $deadline !== '') {
            try {
                return NotificationPresentation::formatDateTime(Carbon::parse($deadline));
            } catch (\Throwable) {
                return $deadline;
            }
        }

        return 'non renseignée';
    }
}
