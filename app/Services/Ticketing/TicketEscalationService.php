<?php

namespace App\Services\Ticketing;

use App\Models\Ticket;
use App\Models\User;

class TicketEscalationService
{
    public function __construct(
        private readonly TicketAssignmentService $assignments,
    ) {}

    /**
     * Escalade fonctionnelle : délègue à l’affectation (historique + notif).
     */
    public function escalate(
        Ticket $ticket,
        ?User $actor,
        ?int $toTeamId = null,
        ?int $toUserId = null,
        ?string $reason = null,
    ): Ticket {
        return $this->assignments->escalate($ticket, $actor, $toTeamId, $toUserId, $reason);
    }
}
