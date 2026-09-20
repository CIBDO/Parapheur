<?php

namespace App\Services\Ticketing;

use App\Models\SupportTeamMember;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketNotification;
use Illuminate\Support\Collection;

class TicketNotificationService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(
        Ticket $ticket,
        string $event,
        ?User $actor,
        string $message,
        array $context = [],
    ): void {
        $recipients = $this->resolveRecipients($ticket, $event, $actor, $context);

        foreach ($recipients as $user) {
            try {
                $pref = \App\Models\TicketNotificationPreference::query()
                    ->where('user_id', $user->id)
                    ->first();

                if ($pref && is_array($pref->muted_events) && in_array($event, $pref->muted_events, true)) {
                    continue;
                }

                if ($pref && ! $pref->database_enabled && ! $pref->mail_enabled) {
                    continue;
                }

                $user->notify(new TicketNotification(
                    ticket: $ticket,
                    event: $event,
                    message: $message,
                    actorName: $actor?->name,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, User>
     */
    public function resolveRecipients(
        Ticket $ticket,
        string $event,
        ?User $actor,
        array $context = [],
    ): Collection {
        $ids = collect();

        $assigneeId = $ticket->assignee_id;
        $teamId = $ticket->support_team_id;
        $isConfidential = $ticket->confidentiality === 'CONFIDENTIEL';
        $unassigned = $assigneeId === null;

        $addAssignee = fn () => $assigneeId && $ids->push($assigneeId);
        $addRequester = fn () => $ticket->requester_id && $ids->push($ticket->requester_id);
        $addObservers = function () use ($ids, $ticket) {
            $observerIds = \App\Models\TicketActor::query()
                ->where('ticket_id', $ticket->id)
                ->where('role', 'observer')
                ->pluck('user_id');
            foreach ($observerIds as $id) {
                $ids->push((int) $id);
            }
        };
        $addLeads = function () use ($ids, $teamId, $context) {
            $targetTeam = $context['to_team_id'] ?? $teamId;
            if (! $targetTeam) {
                return;
            }
            $leadIds = SupportTeamMember::query()
                ->where('support_team_id', $targetTeam)
                ->where('is_lead', true)
                ->pluck('user_id');
            foreach ($leadIds as $id) {
                $ids->push((int) $id);
            }
        };
        $addTeamQueue = function () use ($ids, $teamId, $context, $isConfidential, $unassigned) {
            if ($isConfidential || ! $unassigned) {
                return;
            }
            $queueTeam = $context['to_team_id'] ?? $teamId;
            if (! $queueTeam) {
                return;
            }
            $memberIds = SupportTeamMember::query()
                ->where('support_team_id', $queueTeam)
                ->pluck('user_id');
            foreach ($memberIds as $id) {
                $ids->push((int) $id);
            }
        };

        match ($event) {
            'created' => tap(null, function () use ($addRequester, $addTeamQueue, $addObservers, $unassigned) {
                $addRequester();
                $addObservers();
                if ($unassigned) {
                    $addTeamQueue();
                }
            }),
            'assigned', 'transferred' => tap(null, function () use ($addAssignee, $addObservers) {
                $addAssignee();
                $addObservers();
            }),
            'taken_charge' => tap(null, function () use ($addRequester, $addObservers) {
                $addRequester();
                $addObservers();
            }),
            'comment_requester', 'requester_replied' => tap(null, function () use ($addAssignee, $addTeamQueue) {
                $addAssignee();
                $addTeamQueue();
            }),
            'comment_agent' => tap(null, function () use ($addRequester, $addObservers) {
                $addRequester();
                $addObservers();
            }),
            'internal_note' => null,
            'waiting_requester' => $addRequester(),
            'escalated' => tap(null, function () use ($ids, $context, $addLeads, $isConfidential) {
                if (! empty($context['to_user_id'])) {
                    $ids->push((int) $context['to_user_id']);
                }
                $addLeads();
                if (! $isConfidential && ! empty($context['to_team_id']) && empty($context['to_user_id'])) {
                    $memberIds = SupportTeamMember::query()
                        ->where('support_team_id', (int) $context['to_team_id'])
                        ->pluck('user_id');
                    foreach ($memberIds as $id) {
                        $ids->push((int) $id);
                    }
                }
            }),
            'sla_warning', 'ola_warning' => tap(null, function () use ($addAssignee, $addTeamQueue, $addLeads, $ticket) {
                $addAssignee();
                $addTeamQueue();
                $level = (int) ($ticket->priority?->level ?? 99);
                if ($level <= 2) {
                    $addLeads();
                }
            }),
            'sla_breach', 'ola_breach' => tap(null, function () use ($addAssignee, $addTeamQueue, $addLeads) {
                $addAssignee();
                $addTeamQueue();
                $addLeads();
            }),
            'resolved', 'closed', 'solution_accepted', 'approval_accepted', 'approval_refused' => tap(null, function () use ($addRequester, $addAssignee, $addObservers, $event) {
                $addRequester();
                $addObservers();
                if (in_array($event, ['closed', 'solution_accepted'], true)) {
                    $addAssignee();
                }
            }),
            'reopened' => tap(null, function () use ($addAssignee, $addTeamQueue) {
                $addAssignee();
                $addTeamQueue();
            }),
            'priority_changed' => tap(null, function () use ($addAssignee, $addLeads) {
                $addAssignee();
                $addLeads();
            }),
            default => tap(null, function () use ($addAssignee, $addRequester, $addObservers) {
                $addAssignee();
                $addRequester();
                $addObservers();
            }),
        };

        // CONFIDENTIEL : pas de broadcast file — uniquement assigné + leads (+ demandeur si déjà inclus pour ack)
        if ($isConfidential) {
            $allowed = collect([$assigneeId]);
            if ($teamId) {
                $allowed = $allowed->merge(
                    SupportTeamMember::query()
                        ->where('support_team_id', $teamId)
                        ->where('is_lead', true)
                        ->pluck('user_id')
                );
            }
            // Accusés / résolution / clôture / attente : le demandeur reste notifié
            if (in_array($event, ['created', 'taken_charge', 'comment_agent', 'waiting_requester', 'resolved', 'closed'], true)) {
                $allowed->push($ticket->requester_id);
            }
            $ids = $ids->intersect($allowed->filter()->map(fn ($id) => (int) $id)->all());
        }

        $uniqueIds = $ids
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $actor && $id === $actor->id)
            ->values();

        if ($uniqueIds->isEmpty()) {
            return collect();
        }

        return User::query()->whereIn('id', $uniqueIds)->get();
    }
}
