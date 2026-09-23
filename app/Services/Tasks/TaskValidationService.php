<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Events\TaskReturned;
use App\Events\TaskValidated;
use App\Models\Task;
use App\Models\TaskValidation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskValidationService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskAccessService $access,
        private readonly TaskStateMachine $stateMachine,
        private readonly TaskNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function validate(User $actor, Task $task, ?string $comment = null): Task
    {
        if (! $this->access->canValidate($actor, $task)) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $task, $comment) {
            $from = $task->status;
            $this->stateMachine->assertCanTransition($from, TaskStatus::Validee);
            $task->status = TaskStatus::Validee;
            $task->validated_at = now();
            $task->progress = 100;
            $task->save();

            TaskValidation::query()->create([
                'task_id' => $task->id,
                'validator_id' => $actor->id,
                'decision' => 'validee',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $this->tasks->recordHistory($task, $actor, 'validated', $from->value, TaskStatus::Validee->value, $comment);
            $this->audit->log('task.validated', $task, ['actor_id' => $actor->id]);
            $this->notifications->notifyStatus($task, 'validated');
            event(new TaskValidated($task, $actor));

            return $task->fresh(['assignee', 'creator', 'validations']);
        });
    }

    /**
     * @param  array{motif: string, comment?: ?string, new_due_at?: ?string}  $data
     */
    public function returnForCorrection(User $actor, Task $task, array $data): Task
    {
        if (! $this->access->canValidate($actor, $task)) {
            abort(403);
        }
        if (empty($data['motif'])) {
            throw new InvalidArgumentException('Le motif du retour est obligatoire.');
        }

        return DB::transaction(function () use ($actor, $task, $data) {
            $from = $task->status;
            $this->stateMachine->assertCanTransition($from, TaskStatus::Retournee);
            $task->status = TaskStatus::Retournee;
            if (! empty($data['new_due_at'])) {
                $task->due_at = $data['new_due_at'];
            }
            $task->completed_at = null;
            $task->save();

            TaskValidation::query()->create([
                'task_id' => $task->id,
                'validator_id' => $actor->id,
                'decision' => 'retournee',
                'motif' => $data['motif'],
                'comment' => $data['comment'] ?? null,
                'new_due_at' => $data['new_due_at'] ?? null,
                'decided_at' => now(),
            ]);

            $this->tasks->recordHistory($task, $actor, 'returned', $from->value, TaskStatus::Retournee->value, $data['motif']);
            $this->audit->log('task.returned', $task, ['actor_id' => $actor->id, 'motif' => $data['motif']]);
            $this->notifications->notifyStatus($task, 'returned');
            event(new TaskReturned($task, $actor, $data['motif']));

            return $task->fresh(['assignee', 'creator', 'validations']);
        });
    }
}
