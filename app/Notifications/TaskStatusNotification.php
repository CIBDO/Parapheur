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
            ->line('Objet : '.$this->task->title)
            ->line(NotificationPresentation::actionExpected($this->expectedAction()))
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
            'started' => ['Démarrage', 'La tâche « '.$ref.' » est en cours d’exécution.'],
            'waiting' => ['En attente', 'La tâche « '.$ref.' » a été placée en attente.'],
            'published' => ['Tâche publiée', 'La tâche « '.$ref.' » a été publiée.'],
            'completed' => ['Tâche terminée', 'La tâche « '.$ref.' » a été déclarée terminée.'],
            'validation_requested' => [
                'Validation requise — '.$this->task->reference,
                'La tâche « '.$ref.' » a été déclarée terminée et est soumise à votre validation administrative.',
            ],
            'validated' => ['Tâche validée', 'La tâche « '.$ref.' » a été validée.'],
            'returned' => [
                'Retour pour correction — '.$this->task->reference,
                'La tâche « '.$ref.' » vous est renvoyée pour correction. Merci d’apporter les ajustements nécessaires puis de la retransmettre.',
            ],
            'cancelled' => ['Tâche annulée', 'La tâche « '.$ref.' » a été annulée.'],
            'comment' => ['Nouveau commentaire', ($this->extra['author_name'] ?? 'Un agent').' a commenté « '.$ref.' ».'],
            'mention' => ['Vous êtes cité', ($this->extra['author_name'] ?? 'Un agent').' vous cite sur « '.$ref.' ».'],
            'reminder' => [
                'Rappel de suivi — '.$this->task->reference,
                'La tâche « '.$ref.' » nécessite un suivi de votre part au regard de son échéance ou de son état d’avancement.',
            ],
            'escalated' => [
                'Signalement hiérarchique — '.$this->task->reference,
                'La tâche « '.$ref.' » vous est signalée pour information et suivi au niveau de votre responsabilité.',
            ],
            default => ['Mise à jour', 'La tâche « '.$ref.' » a fait l’objet d’une mise à jour.'],
        };
    }

    private function expectedAction(): string
    {
        return match ($this->event) {
            'validation_requested' => 'statuer sur la tâche (valider ou renvoyer pour correction) dans E-Tresor.',
            'returned' => 'apporter les corrections demandées et retransmettre la tâche.',
            'reminder' => 'actualiser le suivi de la tâche avant échéance.',
            'escalated' => 'prendre connaissance du signalement et assurer le suivi utile.',
            default => 'consulter la tâche dans E-Tresor.',
        };
    }
}
