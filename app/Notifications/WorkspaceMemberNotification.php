<?php

namespace App\Notifications;

use App\Models\Workspace;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceMemberNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Workspace $workspace,
        public string $event,
        public string $roleLabel,
        public ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/espace/collaboratifs/'.$this->workspace->id);

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Espace collaboratif', $this->title()))
            ->greeting(NotificationPresentation::greeting((string) ($notifiable->name ?? '')))
            ->line($this->body())
            ->line('Espace : '.$this->workspace->name)
            ->line('Rôle : '.$this->roleLabel)
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Intervenant : '.$this->actorName))
            ->action('Ouvrir l’espace', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
            'event' => $this->event,
            'workspace_id' => $this->workspace->id,
            'url' => '/espace/collaboratifs/'.$this->workspace->id,
            'actor' => $this->actorName,
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'member_added' => 'Invitation à un espace collaboratif',
            'member_role_changed' => 'Rôle mis à jour dans un espace',
            default => 'Mise à jour d’espace collaboratif',
        };
    }

    private function body(): string
    {
        $name = $this->workspace->name;
        $by = $this->actorName ? ' par '.$this->actorName : '';

        return match ($this->event) {
            'member_added' => sprintf(
                'Vous avez été ajouté(e) à l’espace collaboratif « %s »%s, en qualité de %s. Vous pouvez dès à présent consulter et contribuer selon vos droits.',
                $name,
                $by,
                mb_strtolower($this->roleLabel)
            ),
            'member_role_changed' => sprintf(
                'Votre rôle dans l’espace « %s » a été actualisé%s : vous êtes désormais %s.',
                $name,
                $by,
                mb_strtolower($this->roleLabel)
            ),
            default => sprintf('Une mise à jour concerne l’espace collaboratif « %s ».', $name),
        };
    }
}
