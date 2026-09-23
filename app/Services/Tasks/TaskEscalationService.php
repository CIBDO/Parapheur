<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskEscalation;
use App\Models\User;
use App\Notifications\TaskStatusNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TaskEscalationService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly AuditLogger $audit,
    ) {}

    public function processDueEscalations(): int
    {
        if (! config('tasks.escalations.enabled', true)) {
            return 0;
        }

        $count = 0;
        $rules = config('tasks.escalations.rules', []);

        $overdue = Task::query()
            ->overdue()
            ->with(['assignee', 'creator', 'structure', 'validator'])
            ->limit(200)
            ->get();

        foreach ($overdue as $task) {
            $daysOverdue = $task->due_at?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0;
            $priority = $task->priority?->value ?? 'normale';

            foreach ($rules as $rule) {
                $rulePriority = $rule['priority'] ?? '*';
                if ($rulePriority !== '*' && $rulePriority !== $priority) {
                    continue;
                }

                $after = (int) ($rule['after_days_overdue'] ?? 3);
                $level = (int) ($rule['level'] ?? 1);

                if ($daysOverdue < $after) {
                    continue;
                }

                if ($this->alreadyEscalated($task, $level)) {
                    continue;
                }

                if ($this->escalate($task, null, $level, "Escalade auto J+{$daysOverdue} (priorité {$priority})")) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function escalate(Task $task, ?User $actor, int $level = 1, ?string $reason = null): bool
    {
        if (! $task->status?->isOpen()) {
            return false;
        }

        return DB::transaction(function () use ($task, $actor, $level, $reason) {
            if ($this->alreadyEscalated($task, $level)) {
                return false;
            }

            $manager = $this->resolveManager($task);
            $escalation = TaskEscalation::query()->create([
                'task_id' => $task->id,
                'level' => $level,
                'reason' => $reason ?? 'Escalade',
                'notified_user_id' => $manager?->id,
                'triggered_by' => $actor?->id,
                'escalated_at' => now(),
            ]);

            $this->tasks->recordHistory(
                $task,
                $actor,
                'escalated',
                $task->status->value,
                $task->status->value,
                $reason ?? "Escalade niveau {$level}",
                [
                    'level' => $level,
                    'notified_user_id' => $manager?->id,
                    'escalation_id' => $escalation->id,
                ]
            );

            $this->audit->log('task.escalated', $task, [
                'actor_id' => $actor?->id,
                'level' => $level,
                'notified_user_id' => $manager?->id,
            ]);

            $recipients = collect([$manager, $task->creator, $task->validator, $task->assignee])
                ->filter()
                ->unique('id')
                ->values();

            foreach ($recipients as $user) {
                $user->notify(new TaskStatusNotification($task, 'escalated', [
                    'level' => $level,
                    'reason' => $reason,
                ]));
            }

            return true;
        });
    }

    private function alreadyEscalated(Task $task, int $level): bool
    {
        return TaskEscalation::query()
            ->where('task_id', $task->id)
            ->where('level', $level)
            ->exists();
    }

    private function resolveManager(Task $task): ?User
    {
        $task->loadMissing(['structure', 'creator', 'assignee']);

        // Supérieur de structure : premier utilisateur avec rôle encadrement dans la structure
        if ($task->structure_id) {
            $manager = User::query()
                ->where('structure_id', $task->structure_id)
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', [
                        'Chef de section',
                        'Chef de division',
                        'Directeur',
                        'Directeur Général',
                        'Directeur Général Adjoint',
                        'Secrétariat DG',
                    ]);
                })
                ->where('id', '!=', $task->assignee_id)
                ->orderBy('id')
                ->first();

            if ($manager) {
                return $manager;
            }
        }

        return $task->validator ?? $task->creator;
    }
}
