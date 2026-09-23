<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Events\TaskCompleted;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TaskWorkflowService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskAccessService $access,
        private readonly TaskStateMachine $stateMachine,
        private readonly TaskNotificationService $notifications,
        private readonly TaskDependencyService $dependencies,
        private readonly AuditLogger $audit,
    ) {}

    public function publish(User $actor, Task $task): Task
    {
        if ((int) $task->created_by !== (int) $actor->id && ! $this->access->isAdmin($actor)) {
            abort(403);
        }
        if ($task->status !== TaskStatus::Brouillon) {
            abort(422, 'Seuls les brouillons peuvent être publiés.');
        }
        if (! $task->assignee_id) {
            abort(422, 'Un responsable est requis pour publier la tâche.');
        }

        return $this->apply($actor, $task, TaskStatus::Imputee, 'published', 'Tâche imputée', 10);
    }

    public function takeCharge(User $actor, Task $task, ?string $comment = null): Task
    {
        if (! $this->access->canTakeCharge($actor, $task)) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $task, $comment) {
            $from = $task->status;
            $this->stateMachine->assertCanTransition($from, TaskStatus::PriseEnCharge);
            $task->status = TaskStatus::PriseEnCharge;
            $task->taken_charge_by = $actor->id;
            $task->taken_charge_at = now();
            if ((int) $task->progress < 25) {
                $task->progress = 25;
            }
            $task->save();

            $delegationNote = app(TaskDelegationService::class)->actingAsLabel($actor, $task, 'take_charge');
            $historyComment = $comment;
            if ($delegationNote) {
                $historyComment = trim(($comment ? $comment.' — ' : '').$delegationNote);
            }

            $this->tasks->recordHistory(
                $task,
                $actor,
                'taken_charge',
                $from->value,
                TaskStatus::PriseEnCharge->value,
                $historyComment,
                $delegationNote ? ['by_delegation' => true] : []
            );
            $this->audit->log('task.taken_charge', $task, [
                'actor_id' => $actor->id,
                'by_delegation' => (bool) $delegationNote,
            ]);
            $this->notifications->notifyStatus($task, 'taken_charge');

            return $task->fresh(['assignee', 'creator']);
        });
    }

    public function start(User $actor, Task $task): Task
    {
        if (! $this->access->canTakeCharge($actor, $task) && ! $this->access->canUpdate($actor, $task)) {
            abort(403);
        }

        return $this->apply($actor, $task, TaskStatus::EnCours, 'started', 'Travail démarré', 40);
    }

    public function setWaiting(User $actor, Task $task, ?string $comment = null): Task
    {
        if (! $this->access->canUpdate($actor, $task)) {
            abort(403);
        }

        return $this->apply($actor, $task, TaskStatus::EnAttente, 'waiting', $comment ?? 'Mise en attente');
    }

    /**
     * @param  array{summary?: ?string, result?: ?string, progress?: int}  $data
     */
    public function complete(User $actor, Task $task, array $data = []): Task
    {
        if (! $this->access->canComplete($actor, $task)) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $task, $data) {
            $this->dependencies->assertCanComplete($task);

            $from = $task->status;
            $to = TaskStatus::Terminee;
            $this->stateMachine->assertCanTransition($from, $to);

            $task->status = $to;
            $task->completed_at = now();
            $task->progress = (int) ($data['progress'] ?? 100);
            $task->save();

            TaskCompletion::query()->create([
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'summary' => $data['summary'] ?? null,
                'result' => $data['result'] ?? null,
                'completed_at' => now(),
            ]);

            $this->tasks->recordHistory($task, $actor, 'completed', $from->value, $to->value, $data['summary'] ?? 'Tâche terminée');

            // Auto move to A_VALIDER
            $this->stateMachine->assertCanTransition(TaskStatus::Terminee, TaskStatus::AValider);
            $task->status = TaskStatus::AValider;
            $task->save();
            $this->tasks->recordHistory($task, $actor, 'submitted_validation', TaskStatus::Terminee->value, TaskStatus::AValider->value, 'Soumise à validation');

            $this->audit->log('task.completed', $task, ['actor_id' => $actor->id]);
            $this->notifications->notifyStatus($task, 'completed');
            $this->notifications->notifyValidationRequested($task);
            event(new TaskCompleted($task, $actor));

            return $task->fresh(['assignee', 'creator', 'completions']);
        });
    }

    public function cancel(User $actor, Task $task, string $reason): Task
    {
        if (! $this->access->canCancel($actor, $task)) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $task, $reason) {
            $from = $task->status;
            $this->stateMachine->assertCanTransition($from, TaskStatus::Annulee);
            $task->status = TaskStatus::Annulee;
            $task->cancelled_at = now();
            $task->cancel_reason = $reason;
            $task->save();

            $this->tasks->recordHistory($task, $actor, 'cancelled', $from->value, TaskStatus::Annulee->value, $reason);
            $this->audit->log('task.cancelled', $task, ['actor_id' => $actor->id, 'reason' => $reason]);
            $this->notifications->notifyStatus($task, 'cancelled');

            return $task->fresh(['assignee', 'creator']);
        });
    }

    private function apply(User $actor, Task $task, TaskStatus $to, string $event, string $comment, ?int $minProgress = null): Task
    {
        return DB::transaction(function () use ($actor, $task, $to, $event, $comment, $minProgress) {
            $from = $task->status;
            $this->stateMachine->assertCanTransition($from, $to);
            $task->status = $to;
            if ($minProgress !== null && (int) $task->progress < $minProgress) {
                $task->progress = $minProgress;
            }
            $task->save();

            $this->tasks->recordHistory($task, $actor, $event, $from->value, $to->value, $comment);
            $this->audit->log('task.'.$event, $task, ['actor_id' => $actor->id]);
            $this->notifications->notifyStatus($task, $event);

            return $task->fresh(['assignee', 'creator']);
        });
    }
}
