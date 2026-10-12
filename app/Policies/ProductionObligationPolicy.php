<?php

namespace App\Policies;

use App\Models\ProductionObligation;
use App\Models\User;

class ProductionObligationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('production.view')
            || $user->can('production.view_all')
            || $user->can('production.manage')
            || $user->can('admin.access');
    }

    public function view(User $user, ProductionObligation $obligation): bool
    {
        if ($user->can('production.view_all') || $user->can('production.manage') || $user->can('admin.access')) {
            return true;
        }

        return (int) $obligation->producer_id === (int) $user->id
            || (int) $obligation->deputy_id === (int) $user->id
            || (int) $obligation->structure_id === (int) $user->structure_id
            || $obligation->actors()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('production.manage') || $user->can('admin.access');
    }

    public function update(User $user, ProductionObligation $obligation): bool
    {
        return $user->can('production.manage') || $user->can('admin.access');
    }

    public function manage(User $user): bool
    {
        return $user->can('production.manage') || $user->can('admin.access');
    }
}
