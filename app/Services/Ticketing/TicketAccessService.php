<?php

namespace App\Services\Ticketing;

use App\Models\SupportTeamMember;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TicketAccessService
{
    public function canView(User $user, Ticket $ticket): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user->can('ticket.view')) {
            return false;
        }

        if (! $this->canAccessConfidentiality($user, $ticket)) {
            return false;
        }

        if ($ticket->requester_id === $user->id || $ticket->assignee_id === $user->id) {
            return true;
        }

        if ($user->can('ticket.view_all')) {
            return true;
        }

        if ($user->can('ticket.view_team') && $ticket->support_team_id) {
            return $this->isTeamMember($user, $ticket->support_team_id);
        }

        return false;
    }

    public function canEdit(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user) || $user->can('ticket.update');
    }

    public function canAssign(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user)
            || $user->can('ticket.assign')
            || $user->can('ticket.reassign');
    }

    public function canTakeCharge(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user) || $user->can('ticket.take_charge');
    }

    public function canComment(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user)
            || $user->can('ticket.comment')
            || $ticket->requester_id === $user->id;
    }

    public function canInternalNote(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user) || $user->can('ticket.internal_note');
    }

    public function canResolve(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user) || $user->can('ticket.resolve');
    }

    public function canClose(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user)
            || $user->can('ticket.close')
            || $ticket->requester_id === $user->id;
    }

    public function canReopen(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user)
            || $user->can('ticket.reopen')
            || $ticket->requester_id === $user->id;
    }

    public function canEscalate(User $user, Ticket $ticket): bool
    {
        if (! $this->canView($user, $ticket)) {
            return false;
        }

        return $this->isAdmin($user) || $user->can('ticket.escalate');
    }

    /**
     * Scope index : view_all → tout ; view_team → équipes ; sinon demandeur/assigné.
     * CONFIDENTIEL filtré sauf assigné / leads / view_all / admin.
     */
    public function applyVisibility(Builder $query, User $user): Builder
    {
        if ($this->isAdmin($user) || $user->can('ticket.view_all')) {
            return $query;
        }

        $teamIds = $this->userTeamIds($user);

        $query->where(function (Builder $q) use ($user, $teamIds) {
            $q->where('requester_id', $user->id)
                ->orWhere('assignee_id', $user->id);

            if ($user->can('ticket.view_team') && $teamIds !== []) {
                $q->orWhereIn('support_team_id', $teamIds);
            }
        });

        $leadTeamIds = $this->userLeadTeamIds($user);

        $query->where(function (Builder $q) use ($user, $leadTeamIds) {
            $q->where('confidentiality', '!=', 'CONFIDENTIEL')
                ->orWhere('assignee_id', $user->id);

            if ($leadTeamIds !== []) {
                $q->orWhereIn('support_team_id', $leadTeamIds);
            }
        });

        return $query;
    }

    public function canAccessConfidentiality(User $user, Ticket $ticket): bool
    {
        if ($ticket->confidentiality !== 'CONFIDENTIEL') {
            return true;
        }

        if ($this->isAdmin($user) || $user->can('ticket.view_all')) {
            return true;
        }

        if ($ticket->assignee_id === $user->id) {
            return true;
        }

        if ($ticket->support_team_id && $this->isTeamLead($user, $ticket->support_team_id)) {
            return true;
        }

        return false;
    }

    public function isAdmin(User $user): bool
    {
        return $user->can('admin.access') || $user->can('ticket.admin');
    }

    public function isTeamMember(User $user, int $teamId): bool
    {
        return SupportTeamMember::query()
            ->where('user_id', $user->id)
            ->where('support_team_id', $teamId)
            ->exists();
    }

    public function isTeamLead(User $user, int $teamId): bool
    {
        return SupportTeamMember::query()
            ->where('user_id', $user->id)
            ->where('support_team_id', $teamId)
            ->where('is_lead', true)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function userTeamIds(User $user): array
    {
        return SupportTeamMember::query()
            ->where('user_id', $user->id)
            ->pluck('support_team_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function userLeadTeamIds(User $user): array
    {
        return SupportTeamMember::query()
            ->where('user_id', $user->id)
            ->where('is_lead', true)
            ->pluck('support_team_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
