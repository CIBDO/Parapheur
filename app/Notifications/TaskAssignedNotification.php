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
        $due = NotificationPresentation::formatDateTime($this->task->due_at);
        if ($due === '—') {
            $due = 'non renseignée';
        }

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Tâches', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Référence : '.$this->task->reference)
            ->line('Objet : '.$this->task->title)
            ->line('Échéance : '.$due)
            ->line(NotificationPresentation::actionExpected(
                'prendre en charge la tâche dans E-Tresor et en assurer l’exécution dans le délai imparti.'
            ))
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
        return $this->reassigned
            ? 'Réaffectation de tâche — '.$this->task->reference
            : 'Imputation de tâche — '.$this->task->reference;
    }

    private function body(): string
    {
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $ref = $this->task->reference;
        $title = $this->task->title;

        if ($this->reassigned) {
            return sprintf(
                'La tâche « %s » (%s) vous a été réaffectée%s. '
                .'Elle relève désormais de votre responsabilité opérationnelle.',
                $title,
                $ref,
                $by
            );
        }

        return sprintf(
            'La tâche « %s » (%s) vous a été imputée%s dans le cadre du suivi des travaux '
            .'de la Direction Générale du Trésor et de la Comptabilité Publique.',
            $title,
            $ref,
            $by
        );
    }
}
