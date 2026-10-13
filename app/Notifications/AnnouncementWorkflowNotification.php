<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Announcement $announcement,
        public string $event,
        public string $headline,
        public string $message,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Annonces', $this->headline))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->message)
            ->line('Référence : '.$this->announcement->reference)
            ->action('Ouvrir l\'annonce', url('/annonces/gestion/'.$this->announcement->id))
            ->line(NotificationPresentation::FOOTER);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement.workflow',
            'title' => $this->headline,
            'message' => $this->message,
            'event' => $this->event,
            'announcement_id' => $this->announcement->id,
            'url' => '/annonces/gestion/'.$this->announcement->id,
        ];
    }
}
