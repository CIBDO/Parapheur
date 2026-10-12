<?php

namespace App\Notifications;

use App\Models\Instruction;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstructionAssignedNotification extends Notification
{
    use Queueable;

    /**
     * @param  'assignee'|'coordinator'|'recipient'  $profile
     */
    public function __construct(
        public Instruction $instruction,
        public string $profile = 'assignee',
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $due = optional($this->instruction->due_date)->format('d/m/Y') ?: 'non renseignée';

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject($this->domain(), $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Référence : '.($this->instruction->reference ?: '#'.$this->instruction->id))
            ->line('Objet : '.$this->instruction->title)
            ->line('Échéance : '.$due)
            ->action('Consulter', url($this->path()))
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
            'event' => 'assigned',
            'profile' => $this->profile,
            'instruction_id' => $this->instruction->id,
            'document_id' => $this->instruction->document_id,
            'url' => $this->path(),
        ];
    }

    private function domain(): string
    {
        return $this->instruction->isDirective() ? 'Directives' : 'Instructions';
    }

    private function path(): string
    {
        return $this->instruction->document_id
            ? '/parapheur/'.$this->instruction->document_id
            : '/taches/instructions';
    }

    private function title(): string
    {
        $directive = $this->instruction->isDirective();

        return match ($this->profile) {
            'coordinator' => $directive ? 'Coordination d’une directive' : 'Coordination d’une instruction',
            'recipient' => $directive ? 'Directive portée à votre connaissance' : 'Instruction portée à votre connaissance',
            default => $directive ? 'Directive à exécuter' : 'Instruction à exécuter',
        };
    }

    private function body(): string
    {
        $title = $this->instruction->title;
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $directive = $this->instruction->isDirective();
        $kind = $directive ? 'directive' : 'instruction';

        return match ($this->profile) {
            'coordinator' => sprintf(
                'Vous êtes désigné(e) coordonnateur de la %s « %s »%s. Merci d’en suivre l’exécution et d’en rendre compte.',
                $kind,
                $title,
                $by
            ),
            'recipient' => sprintf(
                'La %s « %s » est portée à votre connaissance%s. Aucune exécution directe ne vous est demandée, sauf indication contraire.',
                $kind,
                $title,
                $by
            ),
            default => $directive
                ? sprintf(
                    'Une directive « %s » vous a été adressée%s. Merci d’en prendre connaissance et d’en organiser l’exécution dans le délai fixé.',
                    $title,
                    $by
                )
                : sprintf(
                    'L’instruction « %s » vous a été confiée%s. Merci d’en assurer l’exécution et d’en rendre compte à l’échéance.',
                    $title,
                    $by
                ),
        };
    }
}
