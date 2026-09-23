<?php

namespace App\Services\Tasks;

use App\Enums\DocumentOrigin;
use App\Enums\TaskDependencyRelation;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskDependencyService
{
    public function __construct(
        private readonly TaskAccessService $access,
        private readonly TaskService $tasks,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<TaskDependency>
     */
    public function list(User $actor, Task $task): array
    {
        if (! $this->access->canView($actor, $task)) {
            abort(403);
        }

        return $task->dependencies()
            ->with(['relatedTask:id,reference,title,status,due_at'])
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function add(User $actor, Task $task, int $relatedTaskId, string $relation, bool $syncInverse = true): TaskDependency
    {
        if (! $this->access->canUpdate($actor, $task) && ! $this->access->isAdmin($actor)) {
            abort(403);
        }

        $relationEnum = TaskDependencyRelation::tryFrom($relation)
            ?? throw new InvalidArgumentException('Type de dépendance invalide.');

        if ((int) $relatedTaskId === (int) $task->id) {
            throw new InvalidArgumentException('Une tâche ne peut pas dépendre d’elle-même.');
        }

        $related = Task::query()->findOrFail($relatedTaskId);
        if (! $this->access->canView($actor, $related)) {
            abort(403, 'Tâche liée inaccessible.');
        }

        if ($this->wouldCreateCycle($task->id, $related->id, $relationEnum)) {
            throw new InvalidArgumentException('Cette dépendance créerait un cycle.');
        }

        return DB::transaction(function () use ($actor, $task, $related, $relationEnum, $syncInverse) {
            $dep = TaskDependency::query()->updateOrCreate(
                [
                    'task_id' => $task->id,
                    'related_task_id' => $related->id,
                    'relation' => $relationEnum->value,
                ],
                []
            );

            $inverse = $relationEnum->inverse();
            if ($syncInverse && $inverse) {
                TaskDependency::query()->updateOrCreate(
                    [
                        'task_id' => $related->id,
                        'related_task_id' => $task->id,
                        'relation' => $inverse->value,
                    ],
                    []
                );
            }

            $this->tasks->recordHistory(
                $task,
                $actor,
                'dependency_added',
                $task->status->value,
                $task->status->value,
                sprintf('%s → %s (%s)', $task->reference, $related->reference, $relationEnum->label()),
                ['related_task_id' => $related->id, 'relation' => $relationEnum->value]
            );
            $this->audit->log('task.dependency_added', $task, [
                'actor_id' => $actor->id,
                'related_task_id' => $related->id,
                'relation' => $relationEnum->value,
            ]);

            return $dep->load('relatedTask:id,reference,title,status,due_at');
        });
    }

    public function remove(User $actor, Task $task, TaskDependency $dependency): void
    {
        if ((int) $dependency->task_id !== (int) $task->id) {
            abort(404);
        }
        if (! $this->access->canUpdate($actor, $task) && ! $this->access->isAdmin($actor)) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $task, $dependency) {
            $relation = TaskDependencyRelation::tryFrom((string) $dependency->relation);
            $relatedId = (int) $dependency->related_task_id;
            $relationValue = $dependency->relation;
            $dependency->delete();

            if ($relation?->inverse()) {
                TaskDependency::query()
                    ->where('task_id', $relatedId)
                    ->where('related_task_id', $task->id)
                    ->where('relation', $relation->inverse()->value)
                    ->delete();
            }

            $this->tasks->recordHistory(
                $task,
                $actor,
                'dependency_removed',
                $task->status->value,
                $task->status->value,
                'Dépendance retirée',
                ['related_task_id' => $relatedId, 'relation' => $relationValue]
            );
            $this->audit->log('task.dependency_removed', $task, [
                'actor_id' => $actor->id,
                'related_task_id' => $relatedId,
            ]);
        });
    }

    public function assertCanComplete(Task $task): void
    {
        $deps = $task->dependencies()
            ->whereIn('relation', [
                TaskDependencyRelation::DependsOn->value,
                TaskDependencyRelation::BlockedBy->value,
            ])
            ->with('relatedTask')
            ->get();

        foreach ($deps as $dep) {
            $related = $dep->relatedTask;
            if (! $related) {
                continue;
            }

            $status = $related->status instanceof TaskStatus
                ? $related->status
                : TaskStatus::from((string) $related->status);

            $satisfied = in_array($status, [TaskStatus::Terminee, TaskStatus::Validee], true);
            if (! $satisfied) {
                throw new InvalidArgumentException(sprintf(
                    'Impossible de terminer : dépendance non satisfaite avec %s (%s — statut %s).',
                    $related->reference,
                    TaskDependencyRelation::from($dep->relation)->label(),
                    $status->label()
                ));
            }
        }
    }

    private function wouldCreateCycle(int $fromId, int $toId, TaskDependencyRelation $relation): bool
    {
        if (! in_array($relation, [
            TaskDependencyRelation::Blocks,
            TaskDependencyRelation::DependsOn,
            TaskDependencyRelation::BlockedBy,
        ], true)) {
            return false;
        }

        $start = $relation === TaskDependencyRelation::BlockedBy ? $fromId : $toId;
        $target = $relation === TaskDependencyRelation::BlockedBy ? $toId : $fromId;

        $visited = [];
        $queue = [$start];

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === $target) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $nextIds = TaskDependency::query()
                ->where('task_id', $current)
                ->whereIn('relation', [
                    TaskDependencyRelation::Blocks->value,
                    TaskDependencyRelation::DependsOn->value,
                ])
                ->pluck('related_task_id')
                ->all();

            foreach ($nextIds as $nextId) {
                $queue[] = (int) $nextId;
            }
        }

        return false;
    }
}
