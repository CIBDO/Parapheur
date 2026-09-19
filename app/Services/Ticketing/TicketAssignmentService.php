<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use App\Models\ServiceItem;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketEscalation;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketAssignmentService
{
    public function __construct(
        private readonly TicketStateMachine $stateMachine,
        private readonly TicketNotificationService $notifications,
        private readonly SlaService $sla,
        private readonly AuditLogger $audit,
    ) {}

    public function assign(
        Ticket $ticket,
        User $actor,
        ?int $teamId = null,
        ?int $assigneeId = null,
        ?string $comment = null,
    ): Ticket {
        if (! $teamId && ! $assigneeId) {
            throw new InvalidArgumentException('Une équipe ou un assigné est requis.');
        }

        return DB::transaction(function () use ($ticket, $actor, $teamId, $assigneeId, $comment) {
            $oldStatus = $ticket->status;
            $teamId ??= $ticket->support_team_id;

            $ticket->update([
                'support_team_id' => $teamId,
                'assignee_id' => $assigneeId,
            ]);

            TicketAssignment::query()->create([
                'ticket_id' => $ticket->id,
                'support_team_id' => $teamId,
                'assignee_id' => $assigneeId,
                'assigned_by' => $actor->id,
                'action' => $ticket->assignee_id ? 'assign' : 'assign_team',
                'comment' => $comment,
            ]);

            $targetStatus = TicketStatus::Affecte;
            if ($oldStatus !== $targetStatus && $this->stateMachine->canTransition($oldStatus, $targetStatus)) {
                $this->applyStatus($ticket, $oldStatus, $targetStatus, $actor, $comment);
            }

            $fresh = $ticket->fresh(['assignee', 'team', 'requester', 'priority', 'sla']);

            $this->audit->log('ticket.assigned', $fresh, [
                'actor_id' => $actor->id,
                'support_team_id' => $teamId,
                'assignee_id' => $assigneeId,
            ]);

            $this->notifications->notify(
                $fresh,
                'assigned',
                $actor,
                'Ticket affecté.',
                ['assignee_id' => $assigneeId, 'support_team_id' => $teamId]
            );

            return $fresh;
        });
    }

    public function takeCharge(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor) {
            $oldStatus = $ticket->status;

            $ticket->update([
                'assignee_id' => $actor->id,
                'taken_at' => $ticket->taken_at ?? now(),
            ]);

            TicketAssignment::query()->create([
                'ticket_id' => $ticket->id,
                'support_team_id' => $ticket->support_team_id,
                'assignee_id' => $actor->id,
                'assigned_by' => $actor->id,
                'action' => 'take_charge',
                'comment' => null,
            ]);

            $target = TicketStatus::PrisEnCharge;
            if ($oldStatus !== $target) {
                if (! $this->stateMachine->canTransition($oldStatus, $target)
                    && $this->stateMachine->canTransition($oldStatus, TicketStatus::EnCours)) {
                    $target = TicketStatus::EnCours;
                }
                $this->stateMachine->assertCanTransition($oldStatus, $target);
                $this->applyStatus($ticket, $oldStatus, $target, $actor, null);
            }

            $fresh = $ticket->fresh(['assignee', 'team', 'requester', 'priority', 'sla']);

            $this->audit->log('ticket.taken_charge', $fresh, [
                'actor_id' => $actor->id,
            ]);

            $this->notifications->notify($fresh, 'taken_charge', $actor, 'Ticket pris en charge.');

            return $fresh;
        });
    }

    public function escalate(
        Ticket $ticket,
        User $actor,
        ?int $toTeamId = null,
        ?int $toUserId = null,
        ?string $reason = null,
    ): Ticket {
        if (! $toTeamId && ! $toUserId) {
            throw new InvalidArgumentException('Une équipe ou un utilisateur cible est requis pour l’escalade.');
        }

        return DB::transaction(function () use ($ticket, $actor, $toTeamId, $toUserId, $reason) {
            $oldStatus = $ticket->status;
            $fromTeamId = $ticket->support_team_id;
            $fromUserId = $ticket->assignee_id;

            TicketEscalation::query()->create([
                'ticket_id' => $ticket->id,
                'from_team_id' => $fromTeamId,
                'to_team_id' => $toTeamId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'kind' => 'functional',
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            $ticket->update([
                'support_team_id' => $toTeamId ?? $ticket->support_team_id,
                'assignee_id' => $toUserId,
            ]);

            TicketAssignment::query()->create([
                'ticket_id' => $ticket->id,
                'support_team_id' => $ticket->support_team_id,
                'assignee_id' => $toUserId,
                'assigned_by' => $actor->id,
                'action' => 'escalate',
                'comment' => $reason,
            ]);

            $target = TicketStatus::Escalade;
            if ($oldStatus !== $target && $this->stateMachine->canTransition($oldStatus, $target)) {
                $this->applyStatus($ticket, $oldStatus, $target, $actor, $reason);
            }

            $fresh = $ticket->fresh(['assignee', 'team', 'requester', 'priority', 'sla']);

            $this->audit->log('ticket.escalated', $fresh, [
                'actor_id' => $actor->id,
                'to_team_id' => $toTeamId,
                'to_user_id' => $toUserId,
                'reason' => $reason,
            ]);

            $this->notifications->notify(
                $fresh,
                'escalated',
                $actor,
                $reason ? 'Escalade : '.$reason : 'Ticket escaladé.',
                ['to_team_id' => $toTeamId, 'to_user_id' => $toUserId]
            );

            return $fresh;
        });
    }

    public function autoAssignFromServiceItem(Ticket $ticket): Ticket
    {
        if (! $ticket->service_item_id) {
            return $ticket;
        }

        $item = ServiceItem::query()->find($ticket->service_item_id);
        if (! $item?->support_team_id) {
            return $ticket;
        }

        $ticket->update(['support_team_id' => $item->support_team_id]);

        return $ticket->fresh();
    }

    private function applyStatus(
        Ticket $ticket,
        TicketStatus $from,
        TicketStatus $to,
        User $actor,
        ?string $comment,
    ): void {
        $this->stateMachine->assertCanTransition($from, $to);
        $ticket->update(['status' => $to]);

        TicketStatusHistory::query()->create([
            'ticket_id' => $ticket->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'user_id' => $actor->id,
            'comment' => $comment,
        ]);

        $this->sla->onStatusChange($ticket->fresh(['sla']), $from, $to);
    }
}
