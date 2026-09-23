<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskDelegation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskDelegationService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): TaskDelegation
    {
        $taskId = $data['task_id'] ?? null;
        $task = $taskId ? Task::query()->findOrFail((int) $taskId) : null;

        if ($task && ! $this->access->canAssign($actor, $task) && (int) $task->assignee_id !== (int) $actor->id) {
            abort(403, 'Délégation non autorisée sur cette tâche.');
        }

        if ((int) $data['delegate_id'] === (int) $actor->id) {
            throw new InvalidArgumentException('Impossible de se déléguer à soi-même.');
        }

        return DB::transaction(function () use ($actor, $data, $task) {
            $delegation = TaskDelegation::query()->create([
                'delegator_id' => $actor->id,
                'delegate_id' => (int) $data['delegate_id'],
                'task_id' => $task?->id,
                'starts_on' => $data['starts_on'] ?? now()->toDateString(),
                'ends_on' => $data['ends_on'],
                'allowed_actions' => $data['allowed_actions'] ?? ['take_charge', 'start', 'complete', 'comment'],
                'is_active' => true,
                'reason' => $data['reason'] ?? null,
            ]);

            if ($task) {
                $this->tasks->recordHistory(
                    $task,
                    $actor,
                    'delegated',
                    $task->status->value,
                    $task->status->value,
                    $data['reason'] ?? 'Délégation créée',
                    [
                        'delegator_id' => $actor->id,
                        'delegate_id' => $delegation->delegate_id,
                        'delegation_id' => $delegation->id,
                    ]
                );
            }

            $this->audit->log('task.delegation_created', $task, [
                'actor_id' => $actor->id,
                'delegation_id' => $delegation->id,
                'delegate_id' => $delegation->delegate_id,
            ]);

            return $delegation->load(['delegator:id,name', 'delegate:id,name', 'task:id,reference,title']);
        });
    }

    public function revoke(User $actor, TaskDelegation $delegation): TaskDelegation
    {
        if ((int) $delegation->delegator_id !== (int) $actor->id
            && ! $actor->can('task.manage')
            && ! $this->access->isAdmin($actor)) {
            abort(403);
        }

        $delegation->update(['is_active' => false]);
        $this->audit->log('task.delegation_revoked', $delegation->task, [
            'actor_id' => $actor->id,
            'delegation_id' => $delegation->id,
        ]);

        return $delegation->fresh(['delegator', 'delegate']);
    }

    public function resolveFor(User $actor, Task $task, string $action): ?TaskDelegation
    {
        return TaskDelegation::query()
            ->where('delegate_id', $actor->id)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', now()->toDateString())
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->where(function ($q) use ($task) {
                $q->whereNull('task_id')->orWhere('task_id', $task->id);
            })
            ->where(function ($q) use ($task) {
                $q->where('delegator_id', $task->assignee_id)
                    ->orWhere('delegator_id', $task->validator_id)
                    ->orWhere('delegator_id', $task->created_by);
            })
            ->orderByDesc('task_id')
            ->get()
            ->first(fn (TaskDelegation $d) => $d->allows($action));
    }

    public function actingAsLabel(User $actor, Task $task, string $action): ?string
    {
        $delegation = $this->resolveFor($actor, $task, $action);
        if (! $delegation) {
            return null;
        }

        $delegation->loadMissing('delegator:id,name');

        return sprintf(
            'Action réalisée par %s par délégation de %s',
            $actor->name,
            $delegation->delegator?->name ?? 'N/A'
        );
    }

    /**
     * @return list<TaskDelegation>
     */
    public function listForUser(User $user): array
    {
        return TaskDelegation::query()
            ->with(['delegator:id,name', 'delegate:id,name', 'task:id,reference,title'])
            ->where(function ($q) use ($user) {
                $q->where('delegator_id', $user->id)->orWhere('delegate_id', $user->id);
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->all();
    }
}
