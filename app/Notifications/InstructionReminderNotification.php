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
            ->subject(NotificationPresentation::mailSubject('Parapheur', $this->titleFor($notifiable)))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body($notifiable))
            ->line('Instruction : '.$this->instruction->title)
            ->line('Échéance : '.$due)
            ->action('Consulter l’instruction', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->titleFor($notifiable),
            'message' => $this->body($notifiable),
            'event' => 'instruction',
            'instruction_id' => $this->instruction->id,
            'document_id' => $this->instruction->document_id,
            'url' => $this->instruction->document_id
                ? '/parapheur/'.$this->instruction->document_id
                : '/taches/instructions',
        ];
    }

    private function titleFor(object $notifiable): string
    {
        return $this->isIssuer($notifiable)
            ? 'Instruction en retard — suivi hiérarchique'
            : 'Instruction en retard';
    }

    private function body(object $notifiable): string
    {
        $due = optional($this->instruction->due_date)->format('d/m/Y') ?: 'non renseignée';
        $title = $this->instruction->title;

        if ($this->isIssuer($notifiable)) {
            return 'L’instruction « '.$title.' », que vous avez émise, a dépassé son échéance du '.$due.'. Merci de vérifier l’état d’exécution.';
        }

        return 'L’instruction « '.$title.' », placée sous votre responsabilité, a dépassé son échéance du '.$due.'. Merci d’actualiser le suivi dans les meilleurs délais.';
    }

    private function isIssuer(object $notifiable): bool
    {
        return isset($notifiable->id) && (int) $notifiable->id === (int) $this->instruction->issuer_id;
    }
}