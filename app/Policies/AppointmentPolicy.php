<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentAccessService;

/**
 * Policy fine — délègue à AppointmentAccessService (pattern AccessService du projet).
 */
class AppointmentPolicy
{
    public function __construct(private AppointmentAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view')
            || $user->can('appointments.create')
            || $this->access->isManager($user)
            || $this->access->isDg($user);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->access->canView($user, $appointment);
    }

    public function create(User $user): bool
    {
        return $this->access->canCreate($user);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->access->canManage($user, $appointment);
    }

    public function validate(User $user, Appointment $appointment): bool
    {
        return $this->access->canValidate($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $appointment->status?->allowsPhysicalDelete()
            && ($this->access->canManage($user, $appointment) || $user->can('appointments.delete_draft'));
    }
}
