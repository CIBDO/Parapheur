<?php

namespace App\Policies;

use App\Models\ProductionOccurrence;
use App\Models\ProductionTransmissionRecipient;
use App\Models\User;

class ProductionOccurrencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('production.view')
            || $user->can('production.view_all')
            || $user->can('production.manage')
            || $user->can('admin.access')
            || $user->can('task.view');
    }

    public function view(User $user, ProductionOccurrence $occurrence): bool
    {
        if ($user->can('production.view_all') || $user->can('production.manage') || $user->can('admin.access')) {
            return true;
        }

        return (int) $occurrence->responsible_id === (int) $user->id
            || ($occurrence->structure_id && (int) $occurrence->structure_id === (int) $user->structure_id)
            || $occurrence->obligation?->actors()->where('user_id', $user->id)->exists()
            || ProductionTransmissionRecipient::query()
                ->where('user_id', $user->id)
                ->whereHas('transmission', fn ($query) => $query->where('production_occurrence_id', $occurrence->id))
                ->exists();
    }

    public function update(User $user, ProductionOccurrence $occurrence): bool
    {
        if ($user->can('production.manage') || $user->can('admin.access')) {
            return true;
        }

        return (int) $occurrence->responsible_id === (int) $user->id
            || (int) $occurrence->obligation?->deputy_id === (int) $user->id;
    }

    public function validate(User $user, ProductionOccurrence $occurrence): bool
    {
        if ($user->can('production.manage') || $user->can('admin.access') || $user->can('production.validate')) {
            return true;
        }

        return $occurrence->validations()
            ->where('validator_id', $user->id)
            ->where('decision', 'pending')
            ->exists();
    }

    public function transmit(User $user, ProductionOccurrence $occurrence): bool
    {
        return $this->update($user, $occurrence);
    }
}
