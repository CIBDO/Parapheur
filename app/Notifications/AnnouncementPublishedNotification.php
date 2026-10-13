<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Annonces', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->message());

        if (! $this->announcement->confidentiality->isSensitive()) {
            $mail->line('Référence : '.$this->announcement->reference);
        }

        return $mail
            ->action('Consulter l\'annonce', url('/annonces/'.$this->announcement->id))
            ->line(NotificationPresentation::FOOTER);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement.published',
            'title' => $this->title(),
            'message' => $this->message(),
            'event' => 'published',
            'importance' => $this->announcement->importance?->code,
            'announcement_id' => $this->announcement->id,
            'url' => '/annonces/'.$this->announcement->id,
        ];
    }

    private function title(): string
    {
        if ($this->announcement->confidentiality->value === 'confidential') {
            return 'Communication interne confidentielle';
        }

        return $this->announcement->title;
    }

    private function message(): string
    {
        if ($this->announcement->confidentiality->value === 'confidential') {
            return 'Une communication interne confidentielle vous est adressée. Ouvrez E-Tresor pour en prendre connaissance.';
        }

        if ($this->announcement->confidentiality->value === 'restricted') {
            return 'Une communication interne restreinte est publiée. Le détail est disponible dans E-Tresor.';
        }

        $summary = trim(strip_tags((string) ($this->announcement->summary ?: $this->announcement->body)));

        return $summary !== '' ? $summary : 'Une annonce interne vient d\'être publiée.';
    }
}
