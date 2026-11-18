<?php

namespace App\Policies;

use App\Models\ArchiveRecord;
use App\Models\User;
use App\Services\Archives\ArchiveAccessService;

class ArchiveRecordPolicy
{
    public function __construct(private readonly ArchiveAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canViewModule($user);
    }

    public function view(User $user, ArchiveRecord $record): bool
    {
        return $this->access->canViewRecord($user, $record);
    }

    public function classify(User $user, ArchiveRecord $record): bool
    {
        return ($user->can('archives.classer') || $user->can('archives.administrer') || $user->can('admin.access'))
            && $this->access->canViewRecord($user, $record);
    }

    public function download(User $user, ArchiveRecord $record): bool
    {
        return $this->access->canDownloadRecord($user, $record);
    }
}
