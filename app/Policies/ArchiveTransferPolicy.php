<?php

namespace App\Policies;

use App\Models\ArchiveTransfer;
use App\Models\User;
use App\Services\Archives\ArchiveAccessService;

class ArchiveTransferPolicy
{
    public function __construct(private readonly ArchiveAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canViewModule($user);
    }

    public function view(User $user, ArchiveTransfer $transfer): bool
    {
        return $this->access->canViewTransfer($user, $transfer);
    }

    public function create(User $user): bool
    {
        return $user->can('archives.verser') || $user->can('admin.access');
    }

    public function submit(User $user, ArchiveTransfer $transfer): bool
    {
        return $this->create($user) && $this->access->canViewTransfer($user, $transfer);
    }

    public function control(User $user, ArchiveTransfer $transfer): bool
    {
        return ($user->can('archives.valider_versement') || $user->can('admin.access'))
            && $this->access->canViewTransfer($user, $transfer);
    }

    public function accept(User $user, ArchiveTransfer $transfer): bool
    {
        return $this->control($user, $transfer);
    }

    public function reject(User $user, ArchiveTransfer $transfer): bool
    {
        return ($user->can('archives.rejeter_versement') || $user->can('archives.valider_versement') || $user->can('admin.access'))
            && $this->access->canViewTransfer($user, $transfer);
    }

    public function takeOver(User $user, ArchiveTransfer $transfer): bool
    {
        return $this->control($user, $transfer);
    }
}
