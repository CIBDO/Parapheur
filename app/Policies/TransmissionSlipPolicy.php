<?php

namespace App\Policies;

use App\Models\TransmissionSlip;
use App\Models\User;

class TransmissionSlipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('mail.view') || $user->can('admin.access');
    }

    public function view(User $user, TransmissionSlip $slip): bool
    {
        if ($user->can('admin.access') || $user->can('mail.view_all')) {
            return true;
        }

        // Créateur, structure émettrice ou destinataire
        if ($slip->created_by === $user->id) {
            return true;
        }

        if ($slip->from_structure_id && $user->structure_id === $slip->from_structure_id) {
            return true;
        }

        if ($slip->to_structure_id && $user->structure_id === $slip->to_structure_id) {
            return true;
        }

        return false;
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
