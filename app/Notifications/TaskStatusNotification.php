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
        [$title, $line] = $this->copy($notifiable);

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Tâches', $title))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($line)
            ->line('Référence : '.$this->task->reference)
            ->action('Consulter la tâche', url('/taches/'.$this->task->id))
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        [$title, $line] = $this->copy($notifiable);

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
    private function copy(object $notifiable): array
    {
        $ref = $this->task->reference.' — '.$this->task->title;
        $mine = isset($notifiable->id) && (int) $notifiable->id === (int) $this->task->assignee_id;

        return match ($this->event) {
            'taken_charge' => $mine
                ? ['Prise en charge enregistrée', 'Vous avez pris en charge la tâche « '.$ref.' ». Le suivi est désormais à votre charge.']
                : ['Tâche prise en charge', 'La tâche « '.$ref.' » a été prise en charge par l’agent qui en a la charge.'],
            'started' => $mine
                ? ['Exécution en cours', 'L’exécution de la tâche « '.$ref.' » est engagée.']
                : ['Tâche démarrée', 'La tâche « '.$ref.' » est en cours d’exécution.'],
            'completed' => $mine
                ? ['Exécution déclarée', 'Vous avez déclaré terminée la tâche « '.$ref.' ».']
                : ['Tâche terminée', 'La tâche « '.$ref.' » a été déclarée terminée. Merci d’en prendre connaissance.'],
            'validation_requested' => ['Validation demandée', 'La tâche « '.$ref.' » est soumise à votre validation. Merci de statuer dans les meilleurs délais.'],
            'validated' => ['Tâche validée', 'La tâche « '.$ref.' » a été validée.'],
            'returned' => $mine
                ? ['Retour pour correction', 'La tâche « '.$ref.' » vous est retournée pour correction. Merci de la reprendre.']
                : ['Retour pour correction', 'La tâche « '.$ref.' » a été retournée pour correction.'],
            'cancelled' => ['Tâche annulée', 'La tâche « '.$ref.' » a été annulée.'],
            'comment' => ['Nouveau commentaire', ($this->extra['author_name'] ?? 'Un agent').' a commenté la tâche « '.$ref.' ».'],
            'mention' => ['Vous êtes mentionné', ($this->extra['author_name'] ?? 'Un agent').' vous mentionne sur la tâche « '.$ref.' ».'],
            'reminder' => ['Rappel d’échéance', 'Rappel : la tâche « '.$ref.' », dont vous avez la charge, appelle un suivi. Merci d’en vérifier l’avancement.'],
            'escalated' => ['Tâche escaladée', 'La tâche « '.$ref.' » a été escaladée à votre niveau. Merci d’en assurer le pilotage.'],
            default => ['Mise à jour de tâche', 'La tâche « '.$ref.' » a été mise à jour.'],
        };
    }
}
