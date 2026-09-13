<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentWorkflowNotification extends Notification
{
    use Queueable;

    /** @var list<string>|null */
    private ?array $forceChannels = null;

    public function __construct(
        public Document $document,
        public string $event,
        public string $message,
        public ?string $actorName = null,
    ) {}

    /**
     * @param  list<string>  $channels
     */
    public function viaChannels(array $channels): self
    {
        $this->forceChannels = $channels;

        return $this;
    }

    public function via(object $notifiable): array
    {
        if ($this->forceChannels !== null) {
            return $this->forceChannels;
        }

        // Toujours cloche in-app + e-mail (log/smtp/array selon .env)
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/parapheur/'.$this->document->id);

        return (new MailMessage)
            ->subject('[E-Tresor] '.$this->title())
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message)
            ->line('Référence : '.($this->document->reference ?: '#'.$this->document->id))
            ->line('Objet : '.$this->document->object)
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Par : '.$this->actorName))
            ->action('Ouvrir le dossier', $url)
            ->line('Ceci est une notification automatique du parapheur électronique DGTCP.');
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
            'classified' => 'Document classé',
            'archived' => 'Document archivé',
            default => 'Mise à jour de dossier',
        };
    }
}