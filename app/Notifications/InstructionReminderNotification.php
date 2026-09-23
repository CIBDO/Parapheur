<?php

namespace App\Notifications;

use App\Models\Instruction;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstructionReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Instruction $instruction) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->instruction->document_id
            ? url('/parapheur/'.$this->instruction->document_id)
            : url('/taches/instructions');

        $due = optional($this->instruction->due_date)->format('d/m/Y') ?: 'non renseignée';

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Parapheur', 'Instruction en retard'))
            ->greeting(NotificationPresentation::greeting((string) ($notifiable->name ?? '')))
            ->line('Une instruction placée sous votre responsabilité a dépassé son échéance. Merci d’actualiser le suivi dans les meilleurs délais.')
            ->line('Instruction : '.$this->instruction->title)
            ->line('Échéance : '.$due)
            ->action('Consulter l’instruction', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        $due = optional($this->instruction->due_date)->format('d/m/Y') ?: 'non renseignée';

        return [
            'title' => 'Instruction en retard',
            'message' => 'L’instruction « '.$this->instruction->title.' » a dépassé son échéance du '.$due.'. Merci d’en assurer le suivi.',
            'event' => 'instruction',
            'instruction_id' => $this->instruction->id,
            'document_id' => $this->instruction->document_id,
            'url' => $this->instruction->document_id
                ? '/parapheur/'.$this->instruction->document_id
                : '/taches/instructions',
        ];
    }
}