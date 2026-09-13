<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceAccessService;

class WorkspacePolicy
{
    public function __construct(
        private readonly WorkspaceAccessService $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('workspace.access') || $user->can('admin.access');
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return ($user->can('workspace.access') || $user->can('admin.access'))
            && $this->access->canView($user, $workspace);
    }

    public function create(User $user): bool
    {
        return $user->can('workspace.create_shared') || $user->can('admin.access');
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return ($user->can('workspace.manage_own') || $user->can('admin.access'))
            && $this->access->canManage($user, $workspace);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return ($user->can('workspace.manage_own') || $user->can('admin.access'))
            && $this->access->canManage($user, $workspace);
    }

    public function manageQuotas(User $user): bool
    {
        return $user->can('workspace.manage_quotas') || $user->can('admin.access');
    }

    public function contribute(User $user, Workspace $workspace): bool
    {
        return ($user->can('workspace.access') || $user->can('admin.access'))
            && $this->access->canContribute($user, $workspace);
    }

    public function edit(User $user, Workspace $workspace): bool
    {
        return ($user->can('workspace.access') || $user->can('admin.access'))
            && $this->access->canEdit($user, $workspace);
    }

    public function share(User $user, Workspace $workspace): bool
    {
        return ($user->can('workspace.access') || $user->can('admin.access'))
            && $this->access->canShare($user, $workspace);
    }
}
