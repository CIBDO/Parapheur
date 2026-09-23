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

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $due = optional($this->task->due_at)->format('d/m/Y H:i') ?: 'non renseignée';

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Tâches', 'Nouvelle tâche imputée'))
            ->greeting(NotificationPresentation::greeting((string) ($notifiable->name ?? '')))
            ->line('Une tâche vous a été imputée.')
            ->line('Référence : '.$this->task->reference)
            ->line('Objet : '.$this->task->title)
            ->line('Échéance : '.$due)
            ->action('Ouvrir la tâche', url('/taches/'.$this->task->id))
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nouvelle tâche imputée',
            'message' => 'La tâche « '.$this->task->title.' » ('.$this->task->reference.') vous a été imputée.',
            'event' => 'assigned',
            'task_id' => $this->task->id,
            'url' => '/taches/'.$this->task->id,
        ];
    }
}
