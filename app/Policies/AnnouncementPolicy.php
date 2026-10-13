<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('announcement.view') || $user->can('announcement.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('announcement.create')
            || $user->can('announcement.update')
            || $user->can('announcement.manage')
            || $user->can('announcement.approve')
            || $user->can('announcement.publish');
    }
}
