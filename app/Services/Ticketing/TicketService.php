<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketChannel;
use App\Models\TicketComment;
use App\Models\TicketSatisfaction;
use App\Models\TicketStatusHistory;
use App\Models\TicketWorklog;
use App\Models\TicketingSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NumberingService;
use App\Services\PrivateDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly TicketStateMachine $stateMachine,
        private readonly TicketPriorityService $priorities,
        private readonly TicketAssignmentService $assignments,
        private readonly SlaService $sla,
        private readonly TicketNotificationService $notifications,
        private readonly ServiceCatalogService $catalog,
        private readonly PrivateDocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Ticket
    {
        return DB::transaction(function () use ($actor, $data) {
            $channelId = $data['channel_id']
                ?? TicketChannel::query()->where('code', 'PORTAIL')->value('id');

            $impactId = $data['impact_id'] ?? null;
            $urgencyId = $data['urgency_id'] ?? null;
            $priority = $this->priorities->resolveFromMatrix(
                $impactId ? (int) $impactId : null,
                $urgencyId ? (int) $urgencyId : null
            );

            if (! empty($data['service_item_id']) && ! empty($data['custom_fields']) && is_array($data['custom_fields'])) {
                $item = \App\Models\ServiceItem::query()->with('fields')->find($data['service_item_id']);
                if ($item) {
                    $data['custom_fields'] = $this->catalog->validateCustomFields($item, $data['custom_fields']);
                }
            }

            $number = $this->numbering->generateTicketNumber($data['structure_id'] ?? $actor->structure_id);

            $ticket = Ticket::query()->create([
                'number' => $number,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => TicketStatus::Nouveau,
                'confidentiality' => $data['confidentiality'] ?? 'NORMAL',
                'source' => $data['source'] ?? null,
                'location_label' => $data['location_label'] ?? null,
                'channel_id' => $channelId,
                'requester_id' => $data['requester_id'] ?? $actor->id,
                'structure_id' => $data['structure_id'] ?? $actor->structure_id,
                'ticket_type_id' => $data['ticket_type_id'] ?? null,
                'ticket_category_id' => $data['ticket_category_id'] ?? null,
                'service_item_id' => $data['service_item_id'] ?? null,
                'impact_id' => $impactId,
                'urgency_id' => $urgencyId,
                'priority_id' => $priority?->id ?? ($data['priority_id'] ?? null),
                'support_team_id' => $data['support_team_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'application_id' => $data['application_id'] ?? null,
                'asset_id' => $data['asset_id'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
                'is_major_incident' => (bool) ($data['is_major_incident'] ?? false),
            ]);

            $this->assignments->autoAssignFromServiceItem($ticket);
            $ticket->refresh();

            $this->sla->attachOnCreate($ticket);

            TicketStatusHistory::query()->create([
                'ticket_id' => $ticket->id,
                'from_status' => null,
                'to_status' => TicketStatus::Nouveau->value,
                'user_id' => $actor->id,
                'comment' => 'Création du ticket',
            ]);

            $this->audit->log('ticket.created', $ticket, [
                'actor_id' => $actor->id,
                'number' => $ticket->number,
            ]);

            $fresh = $ticket->fresh([
                'requester', 'assignee', 'team', 'priority', 'channel', 'sla', 'serviceItem',
            ]);

            $this->notifications->notify(
                $fresh,
                'created',
                $actor,
                sprintf('Ticket %s créé : %s', $fresh->number, $fresh->title)
            );

            return $fresh;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, User $actor, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $data) {
            $allowed = [
                'title', 'description', 'confidentiality', 'source', 'location_label',
                'channel_id', 'ticket_type_id', 'ticket_category_id', 'service_item_id',
                'impact_id', 'urgency_id', 'application_id', 'asset_id', 'custom_fields',
                'is_major_incident', 'structure_id',
            ];

            $payload = array_intersect_key($data, array_flip($allowed));

            if (array_key_exists('impact_id', $payload) || array_key_exists('urgency_id', $payload)) {
                $impactId = (int) ($payload['impact_id'] ?? $ticket->impact_id);
                $urgencyId = (int) ($payload['urgency_id'] ?? $ticket->urgency_id);
                $priority = $this->priorities->resolveFromMatrix($impactId ?: null, $urgencyId ?: null);
                if ($priority) {
                    $payload['priority_id'] = $priority->id;
                }
            }

            $ticket->update($payload);

            $this->audit->log('ticket.updated', $ticket, [
                'actor_id' => $actor->id,
                'fields' => array_keys($payload),
            ]);

            return $ticket->fresh([
                'requester', 'assignee', 'team', 'priority', 'channel', 'sla',
            ]);
        });
    }

    /**
     * Doublons légers : même demandeur, titre similaire, statuts ouverts, 48 h.
     *
     * @return list<Ticket>
     */
    public function findDuplicates(User $requester, string $title, ?int $excludeId = null): array
    {
        $open = array_diff(TicketStatus::values(), [
            TicketStatus::Cloture->value,
            TicketStatus::Annule->value,
        ]);

        $like = '%'.addcslashes($title, '%_\\').'%';

        $query = Ticket::query()
            ->where('requester_id', $requester->id)
            ->whereIn('status', $open)
            ->where('created_at', '>=', now()->subHours(48))
            ->where('title', 'like', $like)
            ->orderByDesc('created_at')
            ->limit(10);

        if ($excludeId) {
            $query->whereKeyNot($excludeId);
        }

        return $query->get()->all();
    }

    public function transition(
        Ticket $ticket,
        ?User $actor,
        TicketStatus $to,
        ?string $comment = null,
        array $extra = [],
    ): Ticket {
        return DB::transaction(function () use ($ticket, $actor, $to, $comment, $extra) {
            $from = $ticket->status;
            $this->stateMachine->assertCanTransition($from, $to);

            $payload = array_merge(['status' => $to], $extra);
            $ticket->update($payload);

            TicketStatusHistory::query()->create([
                'ticket_id' => $ticket->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $actor?->id,
                'comment' => $comment,
            ]);

            $this->sla->onStatusChange($ticket->fresh(['sla']), $from, $to);

            $this->audit->log('ticket.status_changed', $ticket, [
                'actor_id' => $actor?->id,
                'from' => $from->value,
                'to' => $to->value,
            ]);

            return $ticket->fresh(['requester', 'assignee', 'team', 'priority', 'sla']);
        });
    }

    public function resolve(Ticket $ticket, User $actor, string $summary, bool $awaitValidation = true): Ticket
    {
        $to = $awaitValidation ? TicketStatus::AValider : TicketStatus::Resolu;

        // RESOLU d'abord si on vise A_VALIDER depuis un statut de traitement
        if ($awaitValidation && $this->stateMachine->canTransition($ticket->status, TicketStatus::Resolu)
            && ! $this->stateMachine->canTransition($ticket->status, TicketStatus::AValider)) {
            $ticket = $this->transition($ticket, $actor, TicketStatus::Resolu, $summary, [
                'resolution_summary' => $summary,
                'resolved_at' => now(),
            ]);
            $to = TicketStatus::AValider;
        }

        $fresh = $this->transition($ticket, $actor, $to, $summary, [
            'resolution_summary' => $summary,
            'resolved_at' => $ticket->resolved_at ?? now(),
        ]);

        $this->notifications->notify($fresh, 'resolved', $actor, 'Ticket résolu — validation demandée.');

        return $fresh;
    }

    public function reopen(Ticket $ticket, User $actor, string $reason): Ticket
    {
        $fresh = $this->transition($ticket, $actor, TicketStatus::Reouvert, $reason, [
            'reopen_count' => (int) $ticket->reopen_count + 1,
            'resolved_at' => null,
            'closed_at' => null,
        ]);

        $this->notifications->notify($fresh, 'reopened', $actor, 'Ticket réouvert : '.$reason);

        return $fresh;
    }

    public function close(Ticket $ticket, User $actor, ?string $comment = null): Ticket
    {
        $fresh = $this->transition($ticket, $actor, TicketStatus::Cloture, $comment, [
            'closed_at' => now(),
        ]);

        $this->notifications->notify($fresh, 'closed', $actor, 'Ticket clôturé.');

        return $fresh;
    }

    public function cancel(Ticket $ticket, User $actor, string $reason): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::Annule, $reason, [
            'cancellation_reason' => $reason,
            'closed_at' => now(),
        ]);
    }

    public function wait(Ticket $ticket, User $actor, TicketStatus $waitingStatus, ?string $comment = null): Ticket
    {
        if (! in_array($waitingStatus, [TicketStatus::EnAttenteDemandeur, TicketStatus::EnAttenteTiers], true)) {
            throw new InvalidArgumentException('Statut d’attente invalide.');
        }

        $fresh = $this->transition($ticket, $actor, $waitingStatus, $comment);

        if ($waitingStatus === TicketStatus::EnAttenteDemandeur) {
            $this->notifications->notify($fresh, 'waiting_requester', $actor, 'En attente de votre réponse.');
        }

        return $fresh;
    }

    public function addComment(Ticket $ticket, User $actor, string $body, bool $isInternal = false): TicketComment
    {
        return DB::transaction(function () use ($ticket, $actor, $body, $isInternal) {
            $comment = TicketComment::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            $this->audit->log($isInternal ? 'ticket.internal_note' : 'ticket.commented', $ticket, [
                'actor_id' => $actor->id,
                'comment_id' => $comment->id,
            ]);

            if ($isInternal) {
                $this->notifications->notify($ticket, 'internal_note', $actor, 'Note interne ajoutée.');
            } elseif ($ticket->requester_id === $actor->id) {
                $this->notifications->notify($ticket, 'comment_requester', $actor, 'Nouveau commentaire du demandeur.');
                if ($ticket->status === TicketStatus::EnAttenteDemandeur
                    && $this->stateMachine->canTransition($ticket->status, TicketStatus::EnCours)) {
                    $this->transition($ticket, $actor, TicketStatus::EnCours, 'Réponse demandeur');
                    $this->notifications->notify($ticket->fresh(), 'requester_replied', $actor, 'Le demandeur a répondu.');
                }
            } else {
                $this->notifications->notify($ticket, 'comment_agent', $actor, 'Nouveau commentaire sur votre ticket.');
            }

            return $comment->fresh('user');
        });
    }

    public function addAttachment(Ticket $ticket, User $actor, UploadedFile $file): TicketAttachment
    {
        return DB::transaction(function () use ($ticket, $actor, $file) {
            $stored = $this->storage->store($file, 'tickets');

            $attachment = TicketAttachment::query()->create([
                'ticket_id' => $ticket->id,
                'uploaded_by' => $actor->id,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'checksum' => $stored['checksum'],
            ]);

            $this->audit->log('ticket.attachment_added', $ticket, [
                'actor_id' => $actor->id,
                'attachment_id' => $attachment->id,
            ]);

            return $attachment;
        });
    }

    public function deleteAttachment(Ticket $ticket, TicketAttachment $attachment, User $actor): void
    {
        if ((int) $attachment->ticket_id !== (int) $ticket->id) {
            throw new InvalidArgumentException('Pièce jointe hors ticket.');
        }

        DB::transaction(function () use ($ticket, $attachment, $actor) {
            $disk = $attachment->disk;
            $path = $attachment->path;
            $id = $attachment->id;

            $attachment->delete();

            if ($disk && $path && $this->storage->exists($disk, $path)) {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($path);
            }

            $this->audit->log('ticket.attachment_deleted', $ticket, [
                'actor_id' => $actor->id,
                'attachment_id' => $id,
            ]);
        });
    }

    public function addWorklog(Ticket $ticket, User $actor, int $minutes, ?string $note = null, mixed $workedAt = null): TicketWorklog
    {
        return DB::transaction(function () use ($ticket, $actor, $minutes, $note, $workedAt) {
            if ($minutes <= 0) {
                throw new InvalidArgumentException('La durée doit être positive.');
            }

            $worklog = TicketWorklog::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'minutes' => $minutes,
                'note' => $note,
                'worked_at' => $workedAt ? \Carbon\Carbon::parse($workedAt) : now(),
            ]);

            $this->audit->log('ticket.worklog_added', $ticket, [
                'actor_id' => $actor->id,
                'minutes' => $minutes,
            ]);

            return $worklog->fresh('user');
        });
    }

    public function satisfaction(Ticket $ticket, User $actor, int $score, ?string $comment = null): TicketSatisfaction
    {
        if ($score < 1 || $score > 5) {
            throw new InvalidArgumentException('Le score doit être entre 1 et 5.');
        }

        return DB::transaction(function () use ($ticket, $actor, $score, $comment) {
            $row = TicketSatisfaction::query()->updateOrCreate(
                ['ticket_id' => $ticket->id, 'user_id' => $actor->id],
                ['score' => $score, 'comment' => $comment]
            );

            $this->audit->log('ticket.satisfaction', $ticket, [
                'actor_id' => $actor->id,
                'score' => $score,
            ]);

            return $row;
        });
    }

    /**
     * Timeline unifiée (statuts, commentaires filtrés, affectations, priorités).
     *
     * @return list<array<string, mixed>>
     */
    public function timeline(Ticket $ticket, User $viewer, bool $includeInternal = false): array
    {
        $ticket->loadMissing([
            'statusHistories.user',
            'comments.user',
            'assignments.assignedBy',
            'assignments.assignee',
            'priorityHistories.user',
            'priorityHistories.fromPriority',
            'priorityHistories.toPriority',
        ]);

        $events = [];

        foreach ($ticket->statusHistories as $h) {
            $events[] = [
                'type' => 'status',
                'at' => $h->created_at?->toIso8601String(),
                'user' => $h->user?->only(['id', 'name']),
                'from' => $h->from_status,
                'to' => $h->to_status,
                'comment' => $h->comment,
            ];
        }

        foreach ($ticket->comments as $c) {
            if ($c->is_internal && ! $includeInternal) {
                continue;
            }
            $events[] = [
                'type' => $c->is_internal ? 'internal_note' : 'comment',
                'at' => $c->created_at?->toIso8601String(),
                'user' => $c->user?->only(['id', 'name']),
                'body' => $c->body,
                'is_internal' => $c->is_internal,
            ];
        }

        foreach ($ticket->assignments as $a) {
            $events[] = [
                'type' => 'assignment',
                'at' => $a->created_at?->toIso8601String(),
                'user' => $a->assignedBy?->only(['id', 'name']),
                'action' => $a->action,
                'assignee' => $a->assignee?->only(['id', 'name']),
                'comment' => $a->comment,
            ];
        }

        foreach ($ticket->priorityHistories as $p) {
            $events[] = [
                'type' => 'priority',
                'at' => $p->created_at?->toIso8601String(),
                'user' => $p->user?->only(['id', 'name']),
                'from' => $p->fromPriority?->only(['id', 'code', 'name']),
                'to' => $p->toPriority?->only(['id', 'code', 'name']),
                'reason' => $p->reason,
            ];
        }

        usort($events, fn ($a, $b) => strcmp($a['at'] ?? '', $b['at'] ?? ''));

        return $events;
    }

    /**
     * Clôture auto des tickets résolus / à valider trop anciens.
     */
    public function autoCloseResolved(?int $days = null): int
    {
        $days ??= $this->autoCloseDays();
        $threshold = now()->subDays($days);
        $count = 0;

        $tickets = Ticket::query()
            ->whereIn('status', [TicketStatus::Resolu->value, TicketStatus::AValider->value])
            ->where(function ($q) use ($threshold) {
                $q->where('resolved_at', '<=', $threshold)
                    ->orWhere(function ($qq) use ($threshold) {
                        $qq->whereNull('resolved_at')->where('updated_at', '<=', $threshold);
                    });
            })
            ->get();

        foreach ($tickets as $ticket) {
            try {
                if ($ticket->status === TicketStatus::Resolu
                    && $this->stateMachine->canTransition($ticket->status, TicketStatus::AValider)) {
                    $this->transition($ticket, null, TicketStatus::AValider, 'Passage auto à validation');
                    $ticket->refresh();
                }

                if ($this->stateMachine->canTransition($ticket->status, TicketStatus::Cloture)) {
                    $fresh = $this->transition($ticket, null, TicketStatus::Cloture, 'Clôture automatique après '.$days.' jour(s)', [
                        'closed_at' => now(),
                    ]);
                    $this->notifications->notify($fresh, 'closed', null, 'Ticket clôturé automatiquement.');
                    $count++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $count;
    }

    private function autoCloseDays(): int
    {
        $setting = TicketingSetting::query()->where('key', 'auto_close_days')->first();
        $value = $setting?->value;

        if (is_array($value)) {
            return (int) ($value['days'] ?? $value[0] ?? 5);
        }

        return (int) ($value ?? 5);
    }
}
