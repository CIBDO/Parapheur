<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Events\TaskAssigned;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskAssignmentService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskAccessService $access,
        private readonly TaskNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function assign(User $actor, Task $task, int $assigneeId, ?int $structureId = null, ?string $motif = null): Task
    {
        if (! $this->access->canAssign($actor, $task)) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $task, $assigneeId, $structureId, $motif) {
            $previous = $task->assignee_id;
            $from = $task->status;
            $task->assignee_id = $assigneeId;
            if ($structureId) {
                $task->structure_id = $structureId;
            }
            if ($task->status === TaskStatus::Brouillon) {
                $task->status = TaskStatus::Imputee;
            }
            $task->save();

            $this->tasks->recordHistory(
                $task,
                $actor,
                $previous ? 'reassigned' : 'assigned',
                $from->value,
                $task->status->value,
                $motif,
                ['previous_assignee_id' => $previous, 'assignee_id' => $assigneeId]
            );
            $this->audit->log($previous ? 'task.reassigned' : 'task.assigned', $task, [
                'actor_id' => $actor->id,
                'previous_assignee_id' => $previous,
                'assignee_id' => $assigneeId,
            ]);
            $this->notifications->notifyAssigned($task);
            event(new TaskAssigned($task, $actor, $previous));

            return $task->fresh(['assignee', 'creator']);
        });
    }

    public function reassign(User $actor, Task $task, int $assigneeId, string $motif): Task
    {
        if (! $actor->can('task.reassign') && ! $this->access->canAssign($actor, $task)) {
            abort(403);
        }
        if (! $task->assignee_id) {
            throw new InvalidArgumentException('Aucune imputation à réaffecter.');
        }

        return $this->assign($actor, $task, $assigneeId, null, $motif);
    }
}
