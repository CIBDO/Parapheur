<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusNotification;
use Illuminate\Support\Facades\Notification;

class TaskNotificationService
{
    /** Événements qui envoient aussi un e-mail (le reste = app seulement). */
    public const MAIL_EVENTS = [
        'returned',
        'validation_requested',
        'reminder',
        'escalated',
        // mention : in-app seulement (trop bruyant)
    ];

    public function notifyAssigned(Task $task, ?User $actor = null, bool $reassigned = false): void
    {
        $assignee = $task->assignee;
        if (! $assignee || ($actor && (int) $assignee->id === (int) $actor->id)) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification($task, $actor?->name, $reassigned));
    }

    public function notifyStatus(Task $task, string $event, ?User $actor = null): void
    {
        $recipients = $this->recipientsFor($task, $event);
        $recipients = $this->withoutActor($recipients, $actor);

        if ($recipients === []) {
            return;
        }

        Notification::send($recipients, new TaskStatusNotification($task, $event));
    }

    public function notifyValidationRequested(Task $task, ?User $actor = null): void
    {
        $validator = $task->validator ?? $task->creator;
        if (! $validator || ($actor && (int) $validator->id === (int) $actor->id)) {
            return;
        }

        $validator->notify(new TaskStatusNotification($task, 'validation_requested'));
    }

    public function notifyComment(Task $task, User $author, string $body): void
    {
        $recipients = $this->withoutActor($this->stakeholders($task), $author);

        if ($recipients === []) {
            return;
        }

        Notification::send($recipients, new TaskStatusNotification($task, 'comment', [
            'comment_preview' => mb_substr($body, 0, 160),
            'author_name' => $author->name,
        ]));
    }

    /**
     * @param  list<int>  $userIds
     */
    public function notifyMentions(Task $task, User $author, string $body, array $userIds): void
    {
        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('id', '!=', $author->id)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new TaskStatusNotification($task, 'mention', [
            'comment_preview' => mb_substr($body, 0, 160),
            'author_name' => $author->name,
        ]));
    }

    public function notifyReminder(Task $task, string $kind): void
    {
        $assignee = $task->assignee;
        if (! $assignee) {
            return;
        }

        $assignee->notify(new TaskStatusNotification($task, 'reminder', ['kind' => $kind]));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function notifyEscalated(Task $task, ?User $manager, ?User $actor = null, array $extra = []): void
    {
        if (! $manager || ($actor && (int) $manager->id === (int) $actor->id)) {
            return;
        }

        $manager->notify(new TaskStatusNotification($task, 'escalated', $extra));
    }

    public static function sendsMail(string $event): bool
    {
        return in_array($event, self::MAIL_EVENTS, true);
    }

    /**
     * @return list<User>
     */
    private function recipientsFor(Task $task, string $event): array
    {
        $task->loadMissing(['assignee', 'creator', 'validator', 'contributors']);

        return match ($event) {
            'returned' => array_values(array_filter([$task->assignee])),
            'validation_requested' => array_values(array_filter([$task->validator ?? $task->creator])),
            default => $this->stakeholders($task),
        };
    }

    /**
     * @param  list<User>  $recipients
     * @return list<User>
     */
    private function withoutActor(array $recipients, ?User $actor): array
    {
        if (! $actor) {
            return $recipients;
        }

        return collect($recipients)
            ->reject(fn (User $u) => (int) $u->id === (int) $actor->id)
            ->values()
            ->all();
    }

    /**
     * @return list<User>
     */
    private function stakeholders(Task $task): array
    {
        $task->loadMissing(['assignee', 'creator', 'validator', 'contributors']);

        return collect([
            $task->assignee,
            $task->creator,
            $task->validator,
            ...$task->contributors,
        ])
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }
}
