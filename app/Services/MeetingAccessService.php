<?php

namespace App\Services;

use App\Enums\DocumentConfidentiality;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MeetingAccessService
{
    public function isManager(User $user): bool
    {
        return $user->can('admin.access') || $user->can('meetings.manage');
    }

    public function canView(User $user, Meeting $meeting): bool
    {
        if ($this->isOfficer($user, $meeting)) {
            return true;
        }

        if ($this->isParticipant($user, $meeting)) {
            return true;
        }

        $confidentiality = $meeting->confidentiality instanceof DocumentConfidentiality
            ? $meeting->confidentiality
            : DocumentConfidentiality::tryFrom((string) $meeting->confidentiality);

        if ($confidentiality === DocumentConfidentiality::TresConfidentiel) {
            return $user->can('admin.access');
        }

        if ($confidentiality === DocumentConfidentiality::Confidentiel) {
            return $user->can('dashboard.dg') || $user->can('admin.access');
        }

        if ($confidentiality === DocumentConfidentiality::Restreint) {
            return $this->isManager($user)
                || ((int) $user->structure_id === (int) $meeting->structure_id && $user->can('meetings.view'));
        }

        return $user->can('meetings.view') || $this->isManager($user);
    }

    public function canManage(User $user, Meeting $meeting): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $this->isOfficer($user, $meeting) && $user->can('meetings.update');
    }

    public function canCreate(User $user): bool
    {
        return $this->isManager($user) || $user->can('meetings.create');
    }

    public function canEditPreparation(User $user, Meeting $meeting): bool
    {
        $status = $this->status($meeting);

        if ($status->isTerminal() || $status === MeetingStatus::Archivee) {
            return false;
        }

        return $this->canManage($user, $meeting) || $this->isOfficer($user, $meeting);
    }

    public function canManageAgenda(User $user, Meeting $meeting): bool
    {
        if ($this->status($meeting)->agendaLocked() && ! $this->isManager($user)) {
            return $this->isOfficer($user, $meeting) && in_array($this->status($meeting), [
                MeetingStatus::EnCours,
                MeetingStatus::Suspendue,
            ], true);
        }

        return $this->canEditPreparation($user, $meeting)
            || $user->can('meetings.manage_agenda');
    }

    public function canStart(User $user, Meeting $meeting): bool
    {
        return $this->isOfficer($user, $meeting)
            || $this->isManager($user)
            || $user->can('meetings.start');
    }

    public function canTakeOfficialNotes(User $user, Meeting $meeting): bool
    {
        return (int) $meeting->secretary_id === (int) $user->id
            || $this->isManager($user)
            || $user->can('meetings.take_official_notes');
    }

    public function canCreateDecision(User $user, Meeting $meeting): bool
    {
        return $this->isOfficer($user, $meeting)
            || $this->isManager($user)
            || $user->can('meetings.create_decision');
    }

    public function canValidateMinutes(User $user, Meeting $meeting): bool
    {
        return (int) $meeting->chair_id === (int) $user->id
            || $this->isManager($user)
            || $user->can('meetings.validate_minutes');
    }

    public function canViewNote(User $user, MeetingNote $note): bool
    {
        if ($note->isPrivate()) {
            return (int) $note->author_id === (int) $user->id;
        }

        return $this->canView($user, $note->meeting);
    }

    public function isOfficer(User $user, Meeting $meeting): bool
    {
        $id = (int) $user->id;

        return in_array($id, [
            (int) $meeting->created_by,
            (int) $meeting->organizer_id,
            (int) $meeting->chair_id,
            (int) $meeting->secretary_id,
        ], true);
    }

    public function isParticipant(User $user, Meeting $meeting): bool
    {
        if ($meeting->relationLoaded('participants')) {
            return $meeting->participants->contains(fn ($p) => (int) $p->user_id === (int) $user->id);
        }

        return $meeting->participants()->where('user_id', $user->id)->exists();
    }

    public function visibleQuery(User $user): Builder
    {
        $query = Meeting::query();

        if ($user->can('admin.access')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('organizer_id', $user->id)
                ->orWhere('chair_id', $user->id)
                ->orWhere('secretary_id', $user->id)
                ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id));

            if ($user->can('meetings.manage')) {
                $q->orWhereIn('confidentiality', [
                    DocumentConfidentiality::Normal->value,
                    DocumentConfidentiality::Restreint->value,
                    DocumentConfidentiality::Confidentiel->value,
                ]);
            } elseif ($user->can('meetings.view')) {
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

    public function authorizeView(User $user, Meeting $meeting): void
    {
        abort_unless($this->canView($user, $meeting), 403, 'Accès à cette réunion refusé.');
    }

    public function authorizeManage(User $user, Meeting $meeting): void
    {
        abort_unless($this->canManage($user, $meeting) || $this->isOfficer($user, $meeting), 403, 'Action non autorisée sur cette réunion.');
    }

    private function status(Meeting $meeting): MeetingStatus
    {
        return $meeting->status instanceof MeetingStatus
            ? $meeting->status
            : (MeetingStatus::tryFrom((string) $meeting->status) ?? MeetingStatus::Brouillon);
    }
}
