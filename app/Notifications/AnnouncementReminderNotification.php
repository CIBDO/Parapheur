<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementReminderNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Annonces', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->message())
            ->action('Confirmer la lecture', url('/annonces/'.$this->announcement->id))
            ->line('Cette confirmation n\'est pas une signature électronique.')
            ->line(NotificationPresentation::FOOTER);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement.reminder',
            'title' => $this->title(),
            'message' => $this->message(),
            'event' => 'reminder',
            'announcement_id' => $this->announcement->id,
            'url' => '/annonces/'.$this->announcement->id,
        ];
    }

    private function title(): string
    {
        if ($this->announcement->confidentiality->value === 'confidential') {
            return 'Confirmation de lecture attendue';
        }

        return 'Confirmation de lecture — '.$this->announcement->title;
    }

    private function message(): string
    {
        if ($this->announcement->confidentiality->value === 'confidential') {
            return 'Une communication confidentielle attend votre confirmation de lecture dans E-Tresor.';
        }

        return 'Merci de confirmer avoir pris connaissance de cette annonce.';
    }
}
