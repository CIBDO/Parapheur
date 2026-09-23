<?php

namespace App\Notifications;

use App\Models\Task;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public Task $task,
        public string $event,
        public array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$title, $line] = $this->copy();

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Tâches', $title))
            ->greeting(NotificationPresentation::greeting((string) ($notifiable->name ?? '')))
            ->line($line)
            ->line('Référence : '.$this->task->reference)
            ->action('Consulter la tâche', url('/taches/'.$this->task->id))
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        [$title, $line] = $this->copy();

        return array_merge([
            'title' => $title,
            'message' => $line,
            'event' => $this->event,
            'task_id' => $this->task->id,
            'url' => '/taches/'.$this->task->id,
        ], $this->extra);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copy(): array
    {
        $ref = $this->task->reference.' — '.$this->task->title;

        return match ($this->event) {
            'taken_charge' => ['Tâche prise en charge', 'La tâche « '.$ref.' » a été prise en charge.'],
            'started' => ['Tâche démarrée', 'La tâche « '.$ref.' » est en cours.'],
            'completed' => ['Tâche terminée', 'La tâche « '.$ref.' » a été marquée terminée.'],
            'validation_requested' => ['Validation demandée', 'La tâche « '.$ref.' » attend votre validation.'],
            'validated' => ['Tâche validée', 'La tâche « '.$ref.' » a été validée.'],
            'returned' => ['Retour pour correction', 'La tâche « '.$ref.' » a été retournée pour correction.'],
            'cancelled' => ['Tâche annulée', 'La tâche « '.$ref.' » a été annulée.'],
            'comment' => ['Nouveau commentaire', ($this->extra['author_name'] ?? 'Un agent').' a commenté la tâche « '.$ref.' ».'],
            'reminder' => ['Rappel d’échéance', 'Rappel concernant la tâche « '.$ref.' ».'],
            default => ['Mise à jour de tâche', 'La tâche « '.$ref.' » a été mise à jour.'],
        };
    }
}
