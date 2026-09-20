<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketSolution;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketSolutionService
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<TicketSolution>
     */
    public function list(Ticket $ticket): array
    {
        return $ticket->solutions()->with(['author:id,name', 'reviewer:id,name'])->get()->all();
    }

    public function propose(
        Ticket $ticket,
        User $actor,
        string $content,
        string $type = 'solution',
        bool $awaitValidation = true,
    ): TicketSolution {
        return DB::transaction(function () use ($ticket, $actor, $content, $type, $awaitValidation) {
            $solution = TicketSolution::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'solution_type' => in_array($type, ['solution', 'workaround'], true) ? $type : 'solution',
                'status' => 'proposed',
                'content' => $content,
            ]);

            $this->tickets->resolve($ticket, $actor, $content, $awaitValidation);

            $this->audit->log('ticket.solution_proposed', $ticket, [
                'actor_id' => $actor->id,
                'solution_id' => $solution->id,
            ]);

            return $solution->fresh(['author']);
        });
    }

    public function accept(Ticket $ticket, TicketSolution $solution, User $actor): Ticket
    {
        if ((int) $solution->ticket_id !== (int) $ticket->id) {
            throw new InvalidArgumentException('Solution hors ticket.');
        }
        if ($solution->status !== 'proposed') {
            throw new InvalidArgumentException('Cette solution n’est plus en proposition.');
        }

        return DB::transaction(function () use ($ticket, $solution, $actor) {
            $solution->update([
                'status' => 'accepted',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            TicketSolution::query()
                ->where('ticket_id', $ticket->id)
                ->where('status', 'proposed')
                ->whereKeyNot($solution->id)
                ->update(['status' => 'refused', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'refusal_reason' => 'Autre solution acceptée']);

            $ticket->update(['resolution_summary' => $solution->content]);

            $fresh = $this->tickets->close($ticket, $actor, 'Solution acceptée');

            $this->audit->log('ticket.solution_accepted', $ticket, [
                'actor_id' => $actor->id,
                'solution_id' => $solution->id,
            ]);
            $this->notifications->notify(
                $fresh,
                'solution_accepted',
                $actor,
                'Solution acceptée — merci de noter votre satisfaction.'
            );

            return $fresh->fresh(['solutions', 'satisfactions']);
        });
    }

    public function refuse(Ticket $ticket, TicketSolution $solution, User $actor, string $reason): Ticket
    {
        if ((int) $solution->ticket_id !== (int) $ticket->id) {
            throw new InvalidArgumentException('Solution hors ticket.');
        }
        if ($solution->status !== 'proposed') {
            throw new InvalidArgumentException('Cette solution n’est plus en proposition.');
        }

        return DB::transaction(function () use ($ticket, $solution, $actor, $reason) {
            $solution->update([
                'status' => 'refused',
                'refusal_reason' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $fresh = $this->tickets->reopen($ticket, $actor, $reason);

            $this->audit->log('ticket.solution_refused', $ticket, [
                'actor_id' => $actor->id,
                'solution_id' => $solution->id,
            ]);

            return $fresh->fresh(['solutions']);
        });
    }
}
