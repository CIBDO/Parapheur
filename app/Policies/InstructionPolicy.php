<?php

namespace App\Policies;

use App\Models\Instruction;
use App\Models\User;
use App\Services\Tasks\TaskScopeResolver;

class InstructionPolicy
{
    public function __construct(
        private readonly TaskScopeResolver $scope,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('instruction.view')
            || $user->can('instructions.manage')
            || $user->can('task.view')
            || $user->can('admin.access');
    }

    public function view(User $user, Instruction $instruction): bool
    {
        if ($this->scope->isGlobal($user) || $user->can('instructions.manage')) {
            return true;
        }

        if ((int) $instruction->assignee_id === (int) $user->id
            || (int) $instruction->issuer_id === (int) $user->id
            || (int) $instruction->coordinator_id === (int) $user->id) {
            return true;
        }

        if ($instruction->recipients()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($user->can('instruction.view')
            && $instruction->structure_id
            && $this->scope->appliesToStructure($user, (int) $instruction->structure_id)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('instruction.create')
            || $user->can('instructions.manage')
            || $user->can('admin.access');
    }

    public function update(User $user, Instruction $instruction): bool
    {
        return $user->can('instruction.update')
            || $user->can('instructions.manage')
            || $user->can('admin.access')
            || (int) $instruction->issuer_id === (int) $user->id
            || (int) $instruction->assignee_id === (int) $user->id;
    }

    public function assign(User $user, Instruction $instruction): bool
    {
        return $user->can('instruction.assign')
            || $user->can('instructions.manage')
            || $user->can('admin.access')
            || (int) $instruction->issuer_id === (int) $user->id;
    }

    public function close(User $user, Instruction $instruction): bool
    {
        return $user->can('instruction.close')
            || $user->can('instructions.manage')
            || $user->can('admin.access')
            || (int) $instruction->issuer_id === (int) $user->id;
    }

    public function cancel(User $user, Instruction $instruction): bool
    {
        return $user->can('instruction.cancel')
            || $user->can('instructions.manage')
            || $user->can('admin.access')
            || (int) $instruction->issuer_id === (int) $user->id;
    }
}
