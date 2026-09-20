<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticketing\TicketAccessService;
use App\Services\Ticketing\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly TicketAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $paginator = $this->buildQuery($request)->paginate(
            min((int) $request->input('per_page', 20), 100)
        );

        $paginator->getCollection()->transform(fn (Ticket $t) => $this->ticketPayload($t, $request->user()));

        return response()->json($paginator);
    }

    public function search(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'confidentiality' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:100',
            'location_label' => 'nullable|string|max:255',
            'channel_id' => 'nullable|exists:ticket_channels,id',
            'requester_id' => 'nullable|exists:users,id',
            'structure_id' => 'nullable|exists:structures,id',
            'ticket_type_id' => 'nullable|exists:ticket_types,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'service_item_id' => 'nullable|exists:service_items,id',
            'impact_id' => 'nullable|exists:ticket_impact_levels,id',
            'urgency_id' => 'nullable|exists:ticket_urgency_levels,id',
            'priority_id' => 'nullable|exists:ticket_priorities,id',
            'support_team_id' => 'nullable|exists:support_teams,id',
            'assignee_id' => 'nullable|exists:users,id',
            'parent_id' => 'nullable|exists:tickets,id',
            'application_id' => 'nullable|exists:applications,id',
            'asset_id' => 'nullable|exists:assets,id',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'integer|exists:assets,id',
            'observer_ids' => 'nullable|array',
            'observer_ids.*' => 'integer|exists:users,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:ticket_tags,id',
            'custom_fields' => 'nullable|array',
            'is_major_incident' => 'nullable|boolean',
        ]);

        try {
            $ticket = $this->tickets->create($request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->ticketPayload($ticket, $request->user()), 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'requester:id,name,email',
            'assignee:id,name,email',
            'structure:id,code,name',
            'type:id,code,name',
            'category:id,code,name,parent_id',
            'channel:id,code,name',
            'priority:id,code,name,level',
            'impact:id,code,name',
            'urgency:id,code,name',
            'team:id,code,name',
            'serviceItem:id,code,name,requires_approval',
            'sla',
            'ola.policy',
            'tags:id,code,name,color',
            'application:id,code,name',
            'asset:id,inventory_number,name',
            'assets:id,inventory_number,name',
            'actors.user:id,name,email',
            'solutions.author:id,name',
            'approvals',
            'attachments',
            'satisfactions',
            'knowledgeArticles:id,title,summary,slug,status',
        ]);

        $payload = $this->ticketPayload($ticket, $request->user());
        $payload['attachments'] = $ticket->attachments;
        $payload['satisfaction'] = $ticket->satisfactions->sortByDesc('id')->first();
        $payload['actors'] = $ticket->actors;
        $payload['assets'] = $ticket->assets;
        $payload['solutions'] = $ticket->solutions;
        $payload['approvals'] = $ticket->approvals;
        $payload['ola'] = $ticket->ola;
        $payload['knowledge_articles'] = $ticket->knowledgeArticles;

        return response()->json($payload);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'description' => 'nullable|string',
            'confidentiality' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:100',
            'location_label' => 'nullable|string|max:255',
            'channel_id' => 'nullable|exists:ticket_channels,id',
            'ticket_type_id' => 'nullable|exists:ticket_types,id',
            'ticket_category_id' => 'nullable|exists:ticket_categories,id',
            'service_item_id' => 'nullable|exists:service_items,id',
            'impact_id' => 'nullable|exists:ticket_impact_levels,id',
            'urgency_id' => 'nullable|exists:ticket_urgency_levels,id',
            'application_id' => 'nullable|exists:applications,id',
            'asset_id' => 'nullable|exists:assets,id',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'integer|exists:assets,id',
            'observer_ids' => 'nullable|array',
            'observer_ids.*' => 'integer|exists:users,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:ticket_tags,id',
            'custom_fields' => 'nullable|array',
            'is_major_incident' => 'nullable|boolean',
            'structure_id' => 'nullable|exists:structures,id',
        ]);

        $updated = $this->tickets->update($ticket, $request->user(), $validated);

        if (array_key_exists('asset_ids', $validated)) {
            $ids = array_map('intval', $validated['asset_ids'] ?? []);
            $ticket->assets()->sync($ids);
            if ($ids !== []) {
                $ticket->update(['asset_id' => $ids[0]]);
            }
        }
        if (array_key_exists('tag_ids', $validated)) {
            $ticket->tags()->sync(array_map('intval', $validated['tag_ids'] ?? []));
        }
        if (array_key_exists('observer_ids', $validated)) {
            $this->tickets->syncObservers($ticket, $validated['observer_ids'] ?? []);
        }

        return response()->json($this->ticketPayload($updated->fresh(['assets', 'tags', 'actors.user']), $request->user()));
    }

    public function duplicateCheck(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'exclude_id' => 'nullable|integer|exists:tickets,id',
            'requester_id' => 'nullable|exists:users,id',
        ]);

        $requester = $validated['requester_id']
            ? \App\Models\User::query()->findOrFail($validated['requester_id'])
            : $request->user();

        $dupes = $this->tickets->findDuplicates(
            $requester,
            $validated['title'],
            $validated['exclude_id'] ?? null
        );

        return response()->json([
            'duplicates' => array_map(fn (Ticket $t) => $this->ticketPayload($t), $dupes),
        ]);
    }

    public function timeline(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $includeInternal = $this->access->canInternalNote($request->user(), $ticket);

        return response()->json([
            'events' => $this->tickets->timeline($ticket, $request->user(), $includeInternal),
        ]);
    }

    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $validated = $request->validate([
            'team_id' => 'required|exists:support_teams,id',
        ]);

        $query = Ticket::query()
            ->with(['assignee:id,name', 'priority:id,code,name,level', 'sla'])
            ->where('support_team_id', (int) $validated['team_id'])
            ->whereNotIn('status', ['CLOTURE', 'ANNULE']);

        $this->access->applyVisibility($query, $request->user());

        $grouped = $query->orderByDesc('updated_at')->get()
            ->groupBy(fn (Ticket $t) => $t->status?->value ?? (string) $t->status)
            ->map(fn ($items) => $items->map(fn (Ticket $t) => $this->ticketPayload($t))->values());

        return response()->json(['columns' => $grouped]);
    }

    private function buildQuery(Request $request): Builder
    {
        $query = Ticket::query()->with([
            'requester:id,name',
            'assignee:id,name',
            'team:id,code,name',
            'priority:id,code,name,level',
            'channel:id,code,name',
            'category:id,code,name',
            'sla',
        ])->orderByDesc('created_at');

        $this->access->applyVisibility($query, $request->user());

        if ($q = $request->input('q')) {
            $like = '%'.addcslashes((string) $q, '%_\\').'%';
            $query->where(function (Builder $qq) use ($like) {
                $qq->where('number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($status = $request->input('status')) {
            $statuses = is_array($status) ? $status : explode(',', (string) $status);
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('priority_id')) {
            $query->where('priority_id', (int) $request->input('priority_id'));
        }
        if ($request->filled('team_id')) {
            $query->where('support_team_id', (int) $request->input('team_id'));
        }
        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', (int) $request->input('assignee_id'));
        }
        if ($request->filled('requester_id')) {
            $query->where('requester_id', (int) $request->input('requester_id'));
        }
        if ($request->filled('structure_id')) {
            $query->where('structure_id', (int) $request->input('structure_id'));
        }
        if ($request->filled('channel_id')) {
            $query->where('channel_id', (int) $request->input('channel_id'));
        }
        if ($request->filled('ticket_type_id')) {
            $query->where('ticket_type_id', (int) $request->input('ticket_type_id'));
        }
        if ($request->filled('ticket_category_id')) {
            $query->where('ticket_category_id', (int) $request->input('ticket_category_id'));
        }
        if ($request->filled('service_item_id')) {
            $query->where('service_item_id', (int) $request->input('service_item_id'));
        }
        if ($request->filled('confidentiality')) {
            $query->where('confidentiality', (string) $request->input('confidentiality'));
        }
        if ($request->filled('application_id')) {
            $query->where('application_id', (int) $request->input('application_id'));
        }
        if ($request->boolean('is_major_incident')) {
            $query->where('is_major_incident', true);
        }
        if ($request->filled('tag_id')) {
            $tagIds = is_array($request->input('tag_id'))
                ? $request->input('tag_id')
                : explode(',', (string) $request->input('tag_id'));
            $query->whereHas('tags', fn ($q) => $q->whereIn('ticket_tags.id', array_map('intval', $tagIds)));
        }
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->input('created_to'));
        }
        if ($request->filled('due_from')) {
            $query->whereHas('sla', fn ($q) => $q->where('resolution_due_at', '>=', $request->input('due_from')));
        }
        if ($request->filled('due_to')) {
            $query->whereHas('sla', fn ($q) => $q->where('resolution_due_at', '<=', $request->input('due_to')));
        }

        if ($sla = $request->input('sla')) {
            match ($sla) {
                'breach' => $query->whereHas('sla', fn ($q) => $q->where(function ($qq) {
                    $qq->whereNotNull('response_breached_at')->orWhereNotNull('resolution_breached_at');
                })),
                'warning' => $query->whereHas('sla', fn ($q) => $q->whereNotNull('warning_sent_at')
                    ->whereNull('response_breached_at')
                    ->whereNull('resolution_breached_at')),
                'ok' => $query->where(function (Builder $q) {
                    $q->whereDoesntHave('sla')
                        ->orWhereHas('sla', fn ($qq) => $qq->whereNull('warning_sent_at')
                            ->whereNull('response_breached_at')
                            ->whereNull('resolution_breached_at'));
                }),
                default => null,
            };
        }

        if ($request->boolean('mine')) {
            $userId = $request->user()->id;
            $query->where(function (Builder $q) use ($userId) {
                $q->where('assignee_id', $userId)->orWhere('requester_id', $userId);
            });
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assignee_id');
        }

        if ($scope = $request->input('scope')) {
            $user = $request->user();
            match ($scope) {
                'mine' => $query->where(function (Builder $q) use ($user) {
                    $q->where('assignee_id', $user->id)->orWhere('requester_id', $user->id);
                }),
                'assigned' => $query->where('assignee_id', $user->id),
                'requested' => $query->where('requester_id', $user->id),
                'team' => $query->whereIn('support_team_id', $this->access->userTeamIds($user) ?: [0]),
                default => null,
            };
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(Ticket $ticket, ?\App\Models\User $actor = null): array
    {
        $ticket->loadMissing([
            'requester:id,name,email',
            'assignee:id,name,email',
            'structure:id,code,name',
            'type:id,code,name',
            'category:id,code,name',
            'channel:id,code,name',
            'priority:id,code,name,level',
            'impact:id,code,name',
            'urgency:id,code,name',
            'team:id,code,name',
            'serviceItem:id,code,name',
            'application:id,code,name',
            'asset:id,name,inventory_number,location',
            'sla',
            'tags:id,code,name,color',
        ]);

        $payload = [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status?->value ?? $ticket->status,
            'status_label' => $ticket->status?->label(),
            'confidentiality' => $ticket->confidentiality,
            'source' => $ticket->source,
            'location_label' => $ticket->location_label,
            'channel_id' => $ticket->channel_id,
            'requester_id' => $ticket->requester_id,
            'structure_id' => $ticket->structure_id,
            'ticket_type_id' => $ticket->ticket_type_id,
            'ticket_category_id' => $ticket->ticket_category_id,
            'service_item_id' => $ticket->service_item_id,
            'impact_id' => $ticket->impact_id,
            'urgency_id' => $ticket->urgency_id,
            'priority_id' => $ticket->priority_id,
            'support_team_id' => $ticket->support_team_id,
            'assignee_id' => $ticket->assignee_id,
            'parent_id' => $ticket->parent_id,
            'application_id' => $ticket->application_id,
            'asset_id' => $ticket->asset_id,
            'custom_fields' => $ticket->custom_fields,
            'resolution_summary' => $ticket->resolution_summary,
            'cancellation_reason' => $ticket->cancellation_reason,
            'reopen_count' => $ticket->reopen_count,
            'is_major_incident' => (bool) $ticket->is_major_incident,
            'taken_at' => $ticket->taken_at,
            'resolved_at' => $ticket->resolved_at,
            'closed_at' => $ticket->closed_at,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'requester' => $ticket->requester?->only(['id', 'name', 'email']),
            'assignee' => $ticket->assignee?->only(['id', 'name', 'email']),
            'structure' => $ticket->structure?->only(['id', 'code', 'name']),
            'type' => $ticket->type?->only(['id', 'code', 'name']),
            'category' => $ticket->category?->only(['id', 'code', 'name']),
            'channel' => $ticket->channel?->only(['id', 'code', 'name']),
            'priority' => $ticket->priority?->only(['id', 'code', 'name', 'level']),
            'impact' => $ticket->impact?->only(['id', 'code', 'name']),
            'urgency' => $ticket->urgency?->only(['id', 'code', 'name']),
            'team' => $ticket->team?->only(['id', 'code', 'name']),
            'service_item' => $ticket->serviceItem?->only(['id', 'code', 'name']),
            'application' => $ticket->application?->only(['id', 'code', 'name']),
            'asset' => $ticket->asset?->only(['id', 'name', 'inventory_number', 'location']),
            'sla' => $ticket->sla,
            'tags' => $ticket->relationLoaded('tags')
                ? $ticket->tags->map->only(['id', 'code', 'name', 'color'])->values()
                : [],
            'available_actions' => $actor
                ? $this->access->availableActions($actor, $ticket)
                : [],
        ];

        return $payload;
    }
}
