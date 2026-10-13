<?php

namespace App\Notifications;

use App\Models\Document;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstitutionalDocumentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public string $title,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Documentation institutionnelle', $this->title))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->message)
            ->line('Référence : '.($this->document->reference ?: '#'.$this->document->id))
            ->action('Ouvrir le document', url('/ged/'.$this->document->id))
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'document_id' => $this->document->id,
            'reference' => $this->document->reference,
            'url' => '/ged/'.$this->document->id,
        ];
    }
}
