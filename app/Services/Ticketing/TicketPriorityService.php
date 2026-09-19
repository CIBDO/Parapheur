<?php

namespace App\Services\Ticketing;

use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketPriorityHistory;
use App\Models\TicketPriorityMatrix;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketPriorityService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TicketNotificationService $notifications,
    ) {}

    public function resolveFromMatrix(?int $impactId, ?int $urgencyId): ?TicketPriority
    {
        if (! $impactId || ! $urgencyId) {
            return null;
        }

        $entry = TicketPriorityMatrix::query()
            ->where('impact_id', $impactId)
            ->where('urgency_id', $urgencyId)
            ->first();

        if (! $entry) {
            return null;
        }

        return TicketPriority::query()->find($entry->priority_id);
    }

    public function override(Ticket $ticket, int $priorityId, User $actor, string $reason): Ticket
    {
        $priority = TicketPriority::query()->find($priorityId);
        if (! $priority) {
            throw new InvalidArgumentException('Priorité introuvable.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Un motif est requis pour surcharger la priorité.');
        }

        return DB::transaction(function () use ($ticket, $priority, $actor, $reason) {
            $fromId = $ticket->priority_id;

            $ticket->update(['priority_id' => $priority->id]);

            TicketPriorityHistory::query()->create([
                'ticket_id' => $ticket->id,
                'from_priority_id' => $fromId,
                'to_priority_id' => $priority->id,
                'user_id' => $actor->id,
                'reason' => $reason,
            ]);

            $this->audit->log('ticket.priority_overridden', $ticket, [
                'actor_id' => $actor->id,
                'from_priority_id' => $fromId,
                'to_priority_id' => $priority->id,
                'reason' => $reason,
            ]);

            $this->notifications->notify(
                $ticket->fresh(),
                'priority_changed',
                $actor,
                sprintf('Priorité modifiée en %s : %s', $priority->name, $reason),
                ['from_priority_id' => $fromId, 'to_priority_id' => $priority->id]
            );

            return $ticket->fresh(['priority']);
        });
    }
}
