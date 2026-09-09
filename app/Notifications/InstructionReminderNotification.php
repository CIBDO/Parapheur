<?php

namespace App\Notifications;

use App\Models\Instruction;
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
            : url('/parapheur/instructions');

        return (new MailMessage)
            ->subject('[e-Parapheur] Relance instruction en retard')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Une instruction dont vous êtes responsable est en retard.')
            ->line('Titre : '.$this->instruction->title)
            ->line('Échéance : '.optional($this->instruction->due_date)->format('d/m/Y'))
            ->action('Voir l\'instruction', $url)
            ->line('Merci de mettre à jour le suivi dans le parapheur.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Relance instruction en retard',
            'message' => 'Instruction « '.$this->instruction->title.' » échue le '.optional($this->instruction->due_date)->format('d/m/Y'),
            'event' => 'instruction_reminder',
            'instruction_id' => $this->instruction->id,
            'document_id' => $this->instruction->document_id,
            'url' => $this->instruction->document_id
                ? '/parapheur/'.$this->instruction->document_id
                : '/parapheur/instructions',
        ];
    }
}
