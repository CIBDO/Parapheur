<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketApprovalService
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function requestApproval(Ticket $ticket, User $actor): TicketApproval
    {
        $ticket->loadMissing('serviceItem');

        // Point d'ancrage Parapheur : document_id peut être renseigné ultérieurement
        // lorsque le circuit e-Parapheur de la demande est créé (approval_template).
        return TicketApproval::query()->create([
            'ticket_id' => $ticket->id,
            'status' => 'pending',
            'requested_by' => $actor->id,
            'document_id' => $ticket->document_id,
            'comment' => $ticket->serviceItem?->approval_template
                ? 'Circuit Parapheur : '.$ticket->serviceItem->approval_template
                : 'Approbation catalogue requise (validation hiérarchique / Parapheur).',
        ]);
    }

    public function accept(Ticket $ticket, User $actor, ?string $comment = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $comment) {
            $approval = $this->pendingApproval($ticket);
            $approval->update([
                'status' => 'accepted',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'comment' => $comment ?? $approval->comment,
            ]);

            $to = $ticket->support_team_id || $ticket->assignee_id
                ? TicketStatus::Affecte
                : TicketStatus::AQualifier;

            $fresh = $this->tickets->transition($ticket, $actor, $to, $comment ?? 'Approbation acceptée');

            $this->audit->log('ticket.approval_accepted', $ticket, ['actor_id' => $actor->id]);
            $this->notifications->notify($fresh, 'approval_accepted', $actor, 'Demande approuvée — traitement autorisé.');

            return $fresh->fresh(['approvals', 'requester', 'assignee', 'team']);
        });
    }

    public function refuse(Ticket $ticket, User $actor, string $reason): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $reason) {
            $approval = $this->pendingApproval($ticket);
            $approval->update([
                'status' => 'refused',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'comment' => $reason,
            ]);

            $fresh = $this->tickets->cancel($ticket, $actor, $reason);

            $this->audit->log('ticket.approval_refused', $ticket, ['actor_id' => $actor->id]);
            $this->notifications->notify($fresh, 'approval_refused', $actor, 'Demande refusée : '.$reason);

            return $fresh->fresh(['approvals']);
        });
    }

    private function pendingApproval(Ticket $ticket): TicketApproval
    {
        $approval = TicketApproval::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $approval) {
            throw new InvalidArgumentException('Aucune approbation en attente pour ce ticket.');
        }

        return $approval;
    }
}
