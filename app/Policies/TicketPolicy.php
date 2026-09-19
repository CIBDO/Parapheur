<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Ticketing\TicketAccessService;

class TicketPolicy
{
    public function __construct(
        private readonly TicketAccessService $access
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('ticket.view') || $this->access->isAdmin($user);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->access->canView($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->can('ticket.create') || $this->access->isAdmin($user);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->access->canEdit($user, $ticket);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $this->access->canAssign($user, $ticket);
    }

    public function takeCharge(User $user, Ticket $ticket): bool
    {
        return $this->access->canTakeCharge($user, $ticket);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->access->canComment($user, $ticket);
    }

    public function resolve(User $user, Ticket $ticket): bool
    {
        return $this->access->canResolve($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $this->access->canClose($user, $ticket);
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        return $this->access->canReopen($user, $ticket);
    }

    public function escalate(User $user, Ticket $ticket): bool
    {
        return $this->access->canEscalate($user, $ticket);
    }

    public function manageAdmin(User $user): bool
    {
        return $user->can('ticket.admin') || $user->can('admin.access');
    }
}
