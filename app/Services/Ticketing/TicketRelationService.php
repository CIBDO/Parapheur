<?php

namespace App\Services\Ticketing;

use App\Models\Ticket;
use App\Models\TicketRelation;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TicketRelationService
{
    /**
     * @return Collection<int, TicketRelation>
     */
    public function listFor(Ticket $ticket): Collection
    {
        return TicketRelation::query()
            ->where(function ($q) use ($ticket) {
                $q->where('ticket_id', $ticket->id)
                    ->orWhere('related_ticket_id', $ticket->id);
            })
            ->with([
                'ticket:id,number,title,status',
                'relatedTicket:id,number,title,status',
            ])
            ->orderByDesc('id')
            ->get();
    }

    public function link(Ticket $ticket, Ticket $related, string $type = 'related'): TicketRelation
    {
        if ($ticket->id === $related->id) {
            throw new InvalidArgumentException('Un ticket ne peut pas être lié à lui-même.');
        }

        $type = strtolower(trim($type)) ?: 'related';

        return TicketRelation::query()->updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'related_ticket_id' => $related->id,
                'relation_type' => $type,
            ],
            []
        )->load(['ticket:id,number,title,status', 'relatedTicket:id,number,title,status']);
    }

    public function unlink(Ticket $ticket, TicketRelation $relation): void
    {
        if ((int) $relation->ticket_id !== (int) $ticket->id
            && (int) $relation->related_ticket_id !== (int) $ticket->id) {
            throw new InvalidArgumentException('Relation hors périmètre du ticket.');
        }

        $relation->delete();
    }
}
