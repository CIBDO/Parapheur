<?php

namespace App\Services\Tasks;

use App\Enums\DocumentConfidentiality;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class TaskAccessService
{
    public function isAdmin(User $user): bool
    {
        return $user->can('task.manage') || $user->can('admin.access');
    }

    public function canView(User $user, Task $task): bool
    {
        if ($this->isAdmin($user) || $user->can('task.view_all')) {
            return true;
        }

        if ($task->status === TaskStatus::Brouillon && (int) $task->created_by !== (int) $user->id) {
            return false;
        }

        if ($task->confidentiality === DocumentConfidentiality::Confidentiel
            || $task->confidentiality === DocumentConfidentiality::TresConfidentiel) {
            if (! $this->isDirectParticipant($user, $task) && ! $user->can('task.view_all')) {
                return false;
            }
        }

        if ($this->isDirectParticipant($user, $task)) {
            return true;
        }

        if ($user->can('task.view_team') && $user->structure_id && (int) $task->structure_id === (int) $user->structure_id) {
            return true;
        }

        return $user->can('task.view') && (int) $task->created_by === (int) $user->id;
    }

    public function canUpdate(User $user, Task $task): bool
    {
        if ($task->status?->isTerminal()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        return $user->can('task.update')
            && ((int) $task->created_by === (int) $user->id
                || (int) $task->assignee_id === (int) $user->id);
    }

    public function canAssign(User $user, Task $task): bool
    {
        return $this->isAdmin($user)
            || $user->can('task.assign')
            || $user->can('task.reassign')
            || (int) $task->created_by === (int) $user->id;
    }

    public function canTakeCharge(User $user, Task $task): bool
    {
        if (! $user->can('task.take_charge') && ! $this->isAdmin($user)) {
            return false;
        }

        if ((int) $task->assignee_id === (int) $user->id
            || $task->contributors()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return $this->hasActiveDelegation($user, $task, 'take_charge');
    }

    public function canComment(User $user, Task $task): bool
    {
        if ($this->canView($user, $task)
            && ($user->can('task.comment') || $this->isAdmin($user) || $this->isDirectParticipant($user, $task))) {
            return true;
        }

        return $this->hasActiveDelegation($user, $task, 'comment');
    }

    public function canComplete(User $user, Task $task): bool
    {
        if (! $user->can('task.complete') && ! $this->isAdmin($user)) {
            return false;
        }

        if ((int) $task->assignee_id === (int) $user->id || $this->isAdmin($user)) {
            return true;
        }

        return $this->hasActiveDelegation($user, $task, 'complete');
    }

    public function canValidate(User $user, Task $task): bool
    {
        if (! $user->can('task.validate') && ! $this->isAdmin($user)) {
            return false;
        }

        if ($task->validator_id) {
            if ((int) $task->validator_id === (int) $user->id || $this->isAdmin($user)) {
                return true;
            }

            return $this->hasActiveDelegation($user, $task, 'validate');
        }

        return (int) $task->created_by === (int) $user->id
            || $this->isAdmin($user)
            || $this->hasActiveDelegation($user, $task, 'validate');
    }

    public function hasActiveDelegation(User $user, Task $task, string $action): bool
    {
        return app(TaskDelegationService::class)->resolveFor($user, $task, $action) !== null;
    }

    public function canCancel(User $user, Task $task): bool
    {
        return $this->isAdmin($user)
            || ($user->can('task.cancel') && (int) $task->created_by === (int) $user->id);
    }

    public function isDirectParticipant(User $user, Task $task): bool
    {
        if (in_array((int) $user->id, [
            (int) $task->created_by,
            (int) $task->assignee_id,
            (int) $task->validator_id,
        ], true)) {
            return true;
        }

        return $task->contributors()->where('users.id', $user->id)->exists();
    }
}
