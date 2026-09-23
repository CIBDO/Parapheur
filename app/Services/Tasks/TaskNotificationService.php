<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusNotification;
use Illuminate\Support\Facades\Notification;

class TaskNotificationService
{
    public function notifyAssigned(Task $task): void
    {
        $assignee = $task->assignee;
        if (! $assignee) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification($task));
    }

    public function notifyStatus(Task $task, string $event): void
    {
        $recipients = $this->stakeholders($task);
        if ($recipients === []) {
            return;
        }

        Notification::send($recipients, new TaskStatusNotification($task, $event));
    }

    public function notifyValidationRequested(Task $task): void
    {
        $validator = $task->validator ?? $task->creator;
        if (! $validator) {
            return;
        }

        $validator->notify(new TaskStatusNotification($task, 'validation_requested'));
    }

    public function notifyComment(Task $task, User $author, string $body): void
    {
        $recipients = collect($this->stakeholders($task))
            ->reject(fn (User $u) => (int) $u->id === (int) $author->id)
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        Notification::send($recipients, new TaskStatusNotification($task, 'comment', [
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
