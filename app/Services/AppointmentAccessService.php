<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AppointmentAccessService
{
    public function isManager(User $user): bool
    {
        return $user->can('admin.access')
            || $user->can('appointments.manage_requests')
            || $user->can('appointments.manage_calendar');
    }

    public function isDg(User $user): bool
    {
        return $user->can('appointments.validate') || $user->can('dashboard.dg');
    }

    public function canView(User $user, Appointment $appointment): bool
    {
        if ($this->isStakeholder($user, $appointment)) {
            return true;
        }

        $confidentiality = $appointment->confidentiality instanceof DocumentConfidentiality
            ? $appointment->confidentiality
            : DocumentConfidentiality::tryFrom((string) $appointment->confidentiality);

        if ($confidentiality === DocumentConfidentiality::TresConfidentiel) {
            return $user->can('admin.access') || $this->isDg($user) || $this->isManager($user);
        }

        if ($confidentiality === DocumentConfidentiality::Confidentiel) {
            return $this->isDg($user) || $this->isManager($user) || $user->can('admin.access');
        }

        if ($confidentiality === DocumentConfidentiality::Restreint) {
            return $this->isManager($user)
                || $this->isDg($user)
                || ((int) $user->structure_id === (int) $appointment->structure_id && $user->can('appointments.view'));
        }

        return $user->can('appointments.view') || $this->isManager($user) || $this->isDg($user);
    }

    public function canSeeDetails(User $user, Appointment $appointment): bool
    {
        if (! $this->canView($user, $appointment)) {
            return false;
        }

        $confidentiality = $appointment->confidentiality instanceof DocumentConfidentiality
            ? $appointment->confidentiality
            : DocumentConfidentiality::tryFrom((string) $appointment->confidentiality);

        if ($confidentiality === DocumentConfidentiality::TresConfidentiel
            || $confidentiality === DocumentConfidentiality::Confidentiel) {
            return $this->isStakeholder($user, $appointment)
                || $this->isManager($user)
                || $this->isDg($user)
                || $user->can('admin.access');
        }

        return true;
    }

    public function canCreate(User $user): bool
    {
        return $user->can('appointments.create') || $this->isManager($user) || $user->can('admin.access');
    }

    public function canManageRequests(User $user): bool
    {
        return $user->can('appointments.manage_requests') || $user->can('admin.access');
    }

    public function canValidate(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.validate')
            || $user->can('admin.access')
            || ((int) $appointment->director_id === (int) $user->id);
    }

    public function canManage(User $user, Appointment $appointment): bool
    {
        if ($this->isManager($user) || $user->can('admin.access')) {
            return true;
        }

        return $this->isStakeholder($user, $appointment) && $user->can('appointments.update');
    }

    public function canManageNotes(User $user, Appointment $appointment): bool
    {
        return $this->canManage($user, $appointment)
            || $user->can('appointments.manage_notes')
            || $this->isStakeholder($user, $appointment);
    }

    public function canViewNote(User $user, AppointmentNote $note): bool
    {
        if ($note->isPrivate()) {
            return (int) $note->author_id === (int) $user->id;
        }

        return $this->canView($user, $note->appointment);
    }

    public function isStakeholder(User $user, Appointment $appointment): bool
    {
        $id = (int) $user->id;

        if (in_array($id, [
            (int) $appointment->created_by,
            (int) $appointment->director_id,
            (int) $appointment->secretariat_id,
            (int) $appointment->requester_user_id,
            (int) $appointment->validated_by,
        ], true)) {
            return true;
        }

        if ($appointment->relationLoaded('participants')) {
            return $appointment->participants->contains(fn ($p) => (int) $p->user_id === $id);
        }

        return $appointment->participants()->where('user_id', $id)->exists();
    }

    public function visibleQuery(User $user): Builder
    {
        $query = Appointment::query();

        if ($user->can('admin.access')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('director_id', $user->id)
                ->orWhere('secretariat_id', $user->id)
                ->orWhere('requester_user_id', $user->id)
                ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id));

            if ($this->isManager($user) || $this->isDg($user)) {
                $q->orWhereIn('confidentiality', [
                    DocumentConfidentiality::Normal->value,
                    DocumentConfidentiality::Restreint->value,
                    DocumentConfidentiality::Confidentiel->value,
                    DocumentConfidentiality::TresConfidentiel->value,
                ]);
            } elseif ($user->can('appointments.view')) {
                $q->orWhere(function (Builder $visible) use ($user) {
                    $visible->where('confidentiality', DocumentConfidentiality::Normal->value);
                    if ($user->structure_id) {
                        $visible->orWhere(function (Builder $restreint) use ($user) {
                            $restreint->where('confidentiality', DocumentConfidentiality::Restreint->value)
                                ->where('structure_id', $user->structure_id);
                        });
                    }
                });
            }
        });
    }

    public function authorizeView(User $user, Appointment $appointment): void
    {
        abort_unless($this->canView($user, $appointment), 403, 'Accès à ce rendez-vous refusé.');
    }

    public function authorizeManage(User $user, Appointment $appointment): void
    {
        abort_unless($this->canManage($user, $appointment), 403, 'Action non autorisée sur ce rendez-vous.');
    }

    public function authorizeValidate(User $user, Appointment $appointment): void
    {
        abort_unless($this->canValidate($user, $appointment), 403, 'Validation non autorisée.');
    }
}
