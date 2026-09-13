<?php

namespace App\Services;

use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceShareAbility;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceShare;

class WorkspaceAccessService
{
    public function roleFor(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->can('admin.access')) {
            return WorkspaceMemberRole::Owner;
        }

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        return $member?->role;
    }

    public function canView(User $user, Workspace $workspace): bool
    {
        if ($user->can('admin.access')) {
            return true;
        }

        if ($this->roleFor($user, $workspace) !== null) {
            return true;
        }

        return WorkspaceShare::query()
            ->where('workspace_id', $workspace->id)
            ->where('grantee_user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->exists();
    }

    public function canContribute(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role?->canContribute() ?? false;
    }

    public function canEdit(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role?->canEditDocuments() ?? false;
    }

    public function canManage(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role?->canManageMembers() ?? false;
    }

    public function canShare(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role?->canShare() ?? false;
    }

    public function canManageFolders(User $user, Workspace $workspace): bool
    {
        $role = $this->roleFor($user, $workspace);

        return $role?->canManageFolders() ?? false;
    }

    public function highestShareAbility(User $user, Workspace $workspace): ?WorkspaceShareAbility
    {
        $share = WorkspaceShare::query()
            ->where('workspace_id', $workspace->id)
            ->where('grantee_user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->get()
            ->sortByDesc(fn (WorkspaceShare $s) => match ($s->ability) {
                WorkspaceShareAbility::Manage => 4,
                WorkspaceShareAbility::Edit => 3,
                WorkspaceShareAbility::Contribute => 2,
                default => 1,
            })
            ->first();

        return $share?->ability;
    }
}
