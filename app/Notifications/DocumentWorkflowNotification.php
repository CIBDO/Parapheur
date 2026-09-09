<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public string $event,
        public string $message,
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/parapheur/'.$this->document->id);

        return (new MailMessage)
            ->subject('[e-Parapheur] '.$this->title())
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message)
            ->line('Référence : '.($this->document->reference ?: '#'.$this->document->id))
            ->line('Objet : '.$this->document->object)
            ->action('Ouvrir le dossier', $url)
            ->line('Ceci est une notification automatique du parapheur électronique.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message,
            'event' => $this->event,
            'document_id' => $this->document->id,
            'reference' => $this->document->reference,
            'object' => $this->document->object,
            'actor' => $this->actorName,
            'url' => '/parapheur/'.$this->document->id,
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'transmitted' => 'Nouveau document reçu',
            'returned' => 'Document retourné pour correction',
            'vised' => 'Document visé',
            'validated' => 'Document validé',
            'rejected' => 'Document rejeté',
            'commented' => 'Nouveau commentaire',
            'instruction' => 'Nouvelle instruction',
            default => 'Mise à jour de dossier',
        };
    }
}
