<?php

namespace App\Policies;

use App\Models\AnnouncementCategory;
use App\Models\User;

class AnnouncementCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('announcement.view') || $user->can('announcement.manage_categories');
    }

    public function create(User $user): bool
    {
        return $user->can('announcement.manage_categories') || $user->can('announcement.manage');
    }

    public function update(User $user, AnnouncementCategory $category): bool
    {
        return $this->create($user);
    }
}
