<?php

namespace App\Notifications;

use App\Models\Task;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public ?string $actorName = null,
        public bool $reassigned = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $due = optional($this->task->due_at)->format('d/m/Y H:i') ?: 'non renseignée';

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Tâches', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Référence : '.$this->task->reference)
            ->line('Objet : '.$this->task->title)
            ->line('Échéance : '.$due)
            ->action('Ouvrir la tâche', url('/taches/'.$this->task->id))
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
            'event' => $this->reassigned ? 'reassigned' : 'assigned',
            'profile' => 'assignee',
            'task_id' => $this->task->id,
            'url' => '/taches/'.$this->task->id,
        ];
    }

    private function title(): string
    {
        return $this->reassigned ? 'Tâche réaffectée' : 'Nouvelle tâche';
    }

    private function body(): string
    {
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $ref = $this->task->reference.' — '.$this->task->title;

        if ($this->reassigned) {
            return 'La tâche « '.$ref.' » vous a été réaffectée'.$by.'.';
        }

        return 'La tâche « '.$ref.' » vous a été imputée'.$by.'.';
    }
}
