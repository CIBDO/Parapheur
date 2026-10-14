<?php

namespace App\Policies;

use App\Models\TransmissionSlip;
use App\Models\User;
use App\Services\Organization\OrganizationalContextResolver;

class TransmissionSlipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('mail.view') || $user->can('admin.access');
    }

    public function view(User $user, TransmissionSlip $slip): bool
    {
        if ($slip->created_by === $user->id) {
            return true;
        }

        $ids = app(OrganizationalContextResolver::class)->resolve($user)->structureIds;

        if ($slip->from_structure_id && in_array((int) $slip->from_structure_id, $ids, true)) {
            return true;
        }

        return $slip->to_structure_id && in_array((int) $slip->to_structure_id, $ids, true);
    }

    public function create(User $user): bool
    {
        return $user->can('mail.create_transmission_slip') || $user->can('admin.access');
    }

    public function update(User $user, TransmissionSlip $slip): bool
    {
        if (! $this->view($user, $slip)) {
            return false;
        }

        // Seuls les bordereaux en brouillon ou générés peuvent être modifiés
        if (! in_array($slip->status?->value, ['brouillon', 'genere', 'en_modification'], true)) {
            return false;
        }

        return $user->can('mail.update_transmission_slip') || $user->can('admin.access');
    }

    public function delete(User $user, TransmissionSlip $slip): bool
    {
        if (! $this->view($user, $slip)) {
            return false;
        }

        // Seuls les brouillons peuvent être supprimés
        if ($slip->status?->value !== 'brouillon') {
            return false;
        }

        return $user->can('mail.delete_transmission_slip') || $user->can('admin.access');
    }

    public function validate(User $user, TransmissionSlip $slip): bool
    {
        if (! $this->view($user, $slip)) {
            return false;
        }

        return $user->can('mail.validate_transmission_slip') || $user->can('admin.access');
    }

    public function print(User $user, TransmissionSlip $slip): bool
    {
        return $this->view($user, $slip) && ($user->can('mail.update_transmission_slip') || $user->can('admin.access'));
    }

    public function receive(User $user, TransmissionSlip $slip): bool
    {
        return $this->view($user, $slip) && ($user->can('mail.update_transmission_slip') || $user->can('admin.access'));
    }
}
