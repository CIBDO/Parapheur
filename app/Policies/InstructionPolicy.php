<?php

namespace App\Policies;

use App\Models\Instruction;
use App\Models\User;

class InstructionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('instruction.view')
            || $user->can('instructions.manage')
            || $user->can('task.view')
            || $user->can('admin.access');
    }

    public function view(User $user, Instruction $instruction): bool
    {
        if ($user->can('instruction.view') || $user->can('instructions.manage') || $user->can('task.view_all') || $user->can('admin.access')) {
            return true;
        }

        return (int) $instruction->assignee_id === (int) $user->id
            || (int) $instruction->issuer_id === (int) $user->id;
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
