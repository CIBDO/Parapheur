<?php

namespace App\Notifications;

use App\Models\Document;
use App\Support\NotificationPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentWorkflowNotification extends Notification
{
    use Queueable;

    /** Événements qui envoient aussi un e-mail (le reste = app seulement). */
    public const MAIL_EVENTS = [
        'transmitted',
        'returned',
        'rejected',
        'instruction',
        'validated',
        // commented, vised, classified, archived : in-app seulement
    ];

    /** @var list<string>|null */
    private ?array $forceChannels = null;

    public function __construct(
        public Document $document,
        public string $event,
        public string $message = '',
        public ?string $actorName = null,
    ) {}

    /**
     * @param  list<string>  $channels
     */
    public function viaChannels(array $channels): self
    {
        $this->forceChannels = $channels;

        return $this;
    }

    public function via(object $notifiable): array
    {
        if ($this->forceChannels !== null) {
            return $this->forceChannels;
        }

        if (self::sendsMail($this->event)) {
            return ['database', 'mail'];
        }

        return ['database'];
    }

    public static function sendsMail(string $event): bool
    {
        return in_array($event, self::MAIL_EVENTS, true);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/parapheur/'.$this->document->id);

        return (new MailMessage)
            ->subject(NotificationPresentation::mailSubject('Parapheur', $this->title()))
            ->greeting(NotificationPresentation::greeting($notifiable))
            ->line($this->body())
            ->line('Référence : '.$this->reference())
            ->line('Objet : '.$this->document->object)
            ->when($this->actorName, fn (MailMessage $mail) => $mail->line('Intervenant : '.$this->actorName))
            ->line(NotificationPresentation::actionExpected($this->expectedAction()))
            ->action('Ouvrir le dossier', $url)
            ->line(NotificationPresentation::FOOTER);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->body(),
            'event' => $this->event,
            'document_id' => $this->document->id,
            'reference' => $this->document->reference,
            'object' => $this->document->object,
            'actor' => $this->actorName,
            'url' => '/parapheur/'.$this->document->id,
        ];
    }

    private function reference(): string
    {
        return $this->document->reference ?: '#'.$this->document->id;
    }

    private function title(): string
    {
        $ref = $this->reference();

        return match ($this->event) {
            'transmitted' => 'Dossier transmis pour traitement — '.$ref,
            'returned' => 'Retour pour complément — '.$ref,
            'vised' => 'Visa enregistré — '.$ref,
            'validated' => 'Validation administrative — '.$ref,
            'rejected' => 'Rejet de dossier — '.$ref,
            'commented' => 'Observation sur dossier — '.$ref,
            'instruction' => 'Instruction assignée — '.$ref,
            'classified' => 'Classement de dossier — '.$ref,
            'archived' => 'Archivage de dossier — '.$ref,
            default => 'Communication relative au parapheur — '.$ref,
        };
    }

    private function expectedAction(): string
    {
        return match ($this->event) {
            'transmitted' => 'examiner le dossier et accomplir l’acte attendu dans les meilleurs délais.',
            'returned' => 'apporter les compléments ou corrections demandés, puis retransmettre le dossier.',
            'rejected' => 'prendre connaissance du motif de rejet et assurer les suites utiles.',
            'instruction' => 'exécuter l’instruction et en rendre compte dans E-Tresor.',
            'validated' => 'prendre acte de la validation administrative du dossier.',
            default => 'consulter le dossier dans le parapheur électronique.',
        };
    }

    private function body(): string
    {
        $ref = $this->reference();
        $object = (string) $this->document->object;
        $by = $this->actorName ? ' par '.$this->actorName : '';
        $action = $this->document->expected_action?->label();
        $detail = $this->detail();

        return match ($this->event) {
            'transmitted' => sprintf(
                'Le dossier %s (« %s ») vous a été transmis%s%s. Merci d’en assurer le traitement dans les meilleurs délais.',
                $ref,
                $object,
                $by,
                $action ? ' pour '.$action : ''
            ),
            'returned' => sprintf(
                'Le dossier %s (« %s ») a été retourné pour complément ou correction%s. Merci de procéder aux ajustements demandés puis de le retransmettre.',
                $ref,
                $object,
                $by
            ),
            'vised' => sprintf(
                'Le visa a été enregistré sur le dossier %s (« %s »)%s.',
                $ref,
                $object,
                $by
            ),
            'validated' => sprintf(
                'Le dossier %s (« %s ») a été validé administrativement%s.%s',
                $ref,
                $object,
                $by,
                $detail !== '' ? ' '.$detail : ''
            ),
            'rejected' => sprintf(
                'Le dossier %s (« %s ») a été rejeté%s.%s',
                $ref,
                $object,
                $by,
                $detail !== '' ? ' Motif : '.$detail : ''
            ),
            'commented' => sprintf(
                'Un commentaire a été ajouté sur le dossier %s (« %s »)%s.%s',
                $ref,
                $object,
                $by,
                $detail !== '' ? ' Nature : '.$detail : ''
            ),
            'instruction' => sprintf(
                'Une instruction vous a été assignée sur le dossier %s%s.%s',
                $ref,
                $by,
                $detail !== '' ? ' Objet : '.$detail : ''
            ),
            'classified' => sprintf(
                'Le dossier %s (« %s ») a été classé%s.',
                $ref,
                $object,
                $by
            ),
            'archived' => sprintf(
                'Le dossier %s (« %s ») a été archivé%s.',
                $ref,
                $object,
                $by
            ),
            default => $this->message !== ''
                ? $this->message
                : sprintf('Une mise à jour concerne le dossier %s (« %s »).', $ref, $object),
        };
    }

    private function detail(): string
    {
        $message = trim($this->message);
        if ($message === '') {
            return '';
        }

        if (preg_match('/a rejeté le document\s*:\s*(.+)$/iu', $message, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/vous a assigné une instruction\s*:\s*(.+)$/iu', $message, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/a ajouté (un avis|une recommandation|une observation|un commentaire)/iu', $message, $m)) {
            return match (mb_strtolower($m[1])) {
                'un avis' => 'avis',
                'une recommandation' => 'recommandation',
                'une observation' => 'observation',
                default => 'commentaire',
            };
        }

        if (preg_match('/\(par délégation de (.+)\)$/iu', $message, $m)) {
            return 'Validation effectuée par délégation de '.$m[1].'.';
        }

        return '';
    }
}
