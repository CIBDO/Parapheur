<?php

namespace App\Services\Ticketing;

use App\Models\Ticket;
use App\Models\TicketRelation;
use App\Models\User;
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

        $relation = TicketRelation::query()->updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'related_ticket_id' => $related->id,
                'relation_type' => $type,
            ],
            []
        )->load(['ticket:id,number,title,status', 'relatedTicket:id,number,title,status']);

        if ($type === 'parent') {
            $ticket->update(['parent_id' => $related->id]);
        } elseif ($type === 'child') {
            $related->update(['parent_id' => $ticket->id]);
        }

        return $relation;
    }

    /**
     * Fusionne $source dans $target (GLPI-like merge).
     */
    public function merge(Ticket $source, Ticket $target, User $actor): Ticket
    {
        if ($source->id === $target->id) {
            throw new InvalidArgumentException('Impossible de fusionner un ticket avec lui-même.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($source, $target, $actor) {
            $this->link($source, $target, 'duplicate');

            $source->comments()->update(['ticket_id' => $target->id]);
            $source->attachments()->update(['ticket_id' => $target->id]);
            $source->worklogs()->update(['ticket_id' => $target->id]);

            app(TicketService::class)->transition(
                $source,
                $actor,
                \App\Enums\TicketStatus::Cloture,
                sprintf('Fusionné dans %s', $target->number),
                ['closed_at' => now(), 'resolution_summary' => sprintf('Fusionné dans %s', $target->number)]
            );

            return $target->fresh();
        });
    }

    public function unlink(Ticket $ticket, TicketRelation $relation): void
    {
        if ((int) $relation->ticket_id !== (int) $ticket->id
            && (int) $relation->related_ticket_id !== (int) $ticket->id) {
            throw new InvalidArgumentException('Relation hors périmètre du ticket.');
        }

        if ($relation->relation_type === 'parent' && (int) $relation->ticket_id === (int) $ticket->id) {
            $ticket->update(['parent_id' => null]);
        }

        $relation->delete();
    }
}
