<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAccessService;

class TaskPolicy
{
    public function __construct(
        private readonly TaskAccessService $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('task.view')
            || $user->can('task.view_team')
            || $user->can('task.view_all')
            || $this->access->isAdmin($user);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->access->canView($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->can('task.create') || $this->access->isAdmin($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->access->canUpdate($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->access->canAssign($user, $task);
    }

    public function takeCharge(User $user, Task $task): bool
    {
        return $this->access->canTakeCharge($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->access->canComment($user, $task);
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->access->canComplete($user, $task);
    }

    public function validate(User $user, Task $task): bool
    {
        return $this->access->canValidate($user, $task);
    }

    public function returnTask(User $user, Task $task): bool
    {
        return $this->access->canValidate($user, $task);
    }

    public function cancel(User $user, Task $task): bool
    {
        return $this->access->canCancel($user, $task);
    }
}
