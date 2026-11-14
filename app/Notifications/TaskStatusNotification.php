<?php

namespace App\Notifications;

use App\Models\Task;
use App\Services\Tasks\TaskNotificationService;
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
        if (TaskNotificationService::sendsMail($this->event)) {
            return ['database', 'mail'];
        }

        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$title, $line] = $this->copy();

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Tâches', $title))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($line)
            ->line('Référence : '.$this->task->reference)
            ->action('Ouvrir la tâche', url('/taches/'.$this->task->id))
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
            'taken_charge' => ['Prise en charge', 'La tâche « '.$ref.' » a été prise en charge.'],
            'started' => ['Démarrage', 'La tâche « '.$ref.' » est en cours.'],
            'waiting' => ['En attente', 'La tâche « '.$ref.' » est mise en attente.'],
            'published' => ['Tâche publiée', 'La tâche « '.$ref.' » a été publiée.'],
            'completed' => ['Tâche terminée', 'La tâche « '.$ref.' » a été déclarée terminée.'],
            'validation_requested' => ['À valider', 'La tâche « '.$ref.' » attend votre validation.'],
            'validated' => ['Tâche validée', 'La tâche « '.$ref.' » a été validée.'],
            'returned' => ['À corriger', 'La tâche « '.$ref.' » vous est renvoyée pour correction.'],
            'cancelled' => ['Tâche annulée', 'La tâche « '.$ref.' » a été annulée.'],
            'comment' => ['Nouveau commentaire', ($this->extra['author_name'] ?? 'Un agent').' a commenté « '.$ref.' ».'],
            'mention' => ['Vous êtes cité', ($this->extra['author_name'] ?? 'Un agent').' vous cite sur « '.$ref.' ».'],
            'reminder' => ['Rappel', 'Rappel : la tâche « '.$ref.' » demande un suivi.'],
            'escalated' => ['Signalé à la hiérarchie', 'La tâche « '.$ref.' » vous est signalée.'],
            default => ['Mise à jour', 'La tâche « '.$ref.' » a été mise à jour.'],
        };
    }
}
