<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskReminderService
{
    /**
     * @var array<string, int>
     */
    public const OFFSETS = [
        'j_minus_3' => -3,
        'j_minus_1' => -1,
        'j_day' => 0,
        'j_plus_1' => 1,
    ];

    public function __construct(
        private readonly TaskNotificationService $notifications,
    ) {}

    public function scheduleFor(Task $task): void
    {
        if (! $task->due_at || ! $task->status?->isOpen()) {
            $task->reminders()->whereNull('sent_at')->delete();

            return;
        }

        foreach (self::OFFSETS as $kind => $days) {
            $remindAt = $task->due_at->copy()->startOfDay()->addDays($days)->setTime(8, 0);

            TaskReminder::query()->updateOrCreate(
                ['task_id' => $task->id, 'kind' => $kind],
                ['remind_at' => $remindAt, 'sent_at' => null]
            );
        }
    }

    public function processDueReminders(?Carbon $now = null): int
    {
        $now ??= now();
        $sent = 0;

        $reminders = TaskReminder::query()
            ->with(['task.assignee'])
            ->whereNull('sent_at')
            ->where('remind_at', '<=', $now)
            ->whereHas('task', function ($q) {
                $q->whereIn('status', TaskStatus::openValues());
            })
            ->limit(200)
            ->get();

        foreach ($reminders as $reminder) {
            DB::transaction(function () use ($reminder, &$sent) {
                $task = $reminder->task;
                if (! $task || ! $task->status?->isOpen()) {
                    $reminder->update(['sent_at' => now()]);

                    return;
                }

                $this->notifications->notifyReminder($task, $reminder->kind);
                $reminder->update(['sent_at' => now()]);
                $task->update(['updated_at' => now()]);
                $sent++;
            });
        }

        // Overdue catch-up
        $overdue = Task::query()
            ->overdue()
            ->where(function ($q) {
                $q->whereNull('updated_at')
                    ->orWhere('updated_at', '<', now()->subDay());
            })
            ->with('assignee')
            ->limit(100)
            ->get();

        foreach ($overdue as $task) {
            $already = TaskReminder::query()
                ->where('task_id', $task->id)
                ->where('kind', 'overdue')
                ->whereDate('sent_at', now()->toDateString())
                ->exists();
            if ($already) {
                continue;
            }

            $this->notifications->notifyReminder($task, 'overdue');
            TaskReminder::query()->updateOrCreate(
                ['task_id' => $task->id, 'kind' => 'overdue'],
                ['remind_at' => now(), 'sent_at' => now()]
            );
            $sent++;
        }

        return $sent;
    }
}
