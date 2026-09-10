<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeetingDecisionStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\MeetingRecommendation;
use App\Services\AuditLogger;
use App\Services\MeetingAccessService;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingDecisionController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly MeetingAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MeetingDecision::query()->with(['meeting.type', 'assignee', 'structure', 'instruction']);

        foreach (['status', 'structure_id', 'assignee_id', 'meeting_id', 'priority'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('due_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('due_date', '<=', $request->date('to'));
        }
        if ($request->boolean('late')) {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '<', now())
                ->whereNotIn('status', ['executee', 'cloturee', 'annulee']);
        }

        $paginator = $query->orderByRaw('due_date is null')->orderBy('due_date')->paginate($request->integer('per_page') ?: 20);
        $visible = $paginator->getCollection()->filter(function (MeetingDecision $decision) use ($request) {
            return $decision->meeting && $this->access->canView($request->user(), $decision->meeting);
        })->values()->map(fn (MeetingDecision $d) => [
            ...$d->toArray(),
            'status' => $d->effectiveStatus()->value,
            'status_label' => $d->effectiveStatus()->label(),
            'is_overdue' => $d->isOverdue(),
            'meeting' => $d->meeting?->only(['id', 'reference', 'title', 'object', 'meeting_date']),
        ]);

        $paginator->setCollection($visible);

        $statsQuery = MeetingDecision::query();
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'executee' => (clone $statsQuery)->where('status', 'executee')->count(),
            'en_cours' => (clone $statsQuery)->whereIn('status', ['a_faire', 'planifiee', 'en_cours', 'en_attente'])->count(),
            'partiellement_executee' => (clone $statsQuery)->where('status', 'partiellement_executee')->count(),
            'en_retard' => (clone $statsQuery)->whereNotNull('due_date')->whereDate('due_date', '<', now())->whereNotIn('status', ['executee', 'cloturee', 'annulee'])->count(),
        ];

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'stats' => $stats,
        ]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($this->access->canCreateDecision($request->user(), $meeting), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string'],
            'agenda_item_id' => ['nullable', 'exists:meeting_agenda_items,id'],
            'observations' => ['nullable', 'string'],
            'create_instruction' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->meetings->addDecision($request->user(), $meeting, $data), 201);
    }

    public function update(Request $request, Meeting $meeting, MeetingDecision $decision): JsonResponse
    {
        abort_unless($decision->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canCreateDecision($request->user(), $meeting) || (int) $decision->assignee_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(MeetingDecisionStatus::class)],
            'observations' => ['nullable', 'string'],
            'execution_comment' => ['nullable', 'string'],
            'justification_document_id' => ['nullable', 'exists:documents,id'],
            'create_instruction' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['status']) && in_array((string) $data['status'], ['executee', 'partiellement_executee'], true)) {
            $data['executed_at'] = $data['executed_at'] ?? now();
            $data['execution_declared_by'] = $request->user()->id;
        }

        $decision->update(collect($data)->except('create_instruction')->all());

        if ($request->boolean('create_instruction') && $decision->assignee_id && ! $decision->instruction) {
            $this->meetings->createInstructionFromDecision($request->user(), $decision);
        }

        $this->audit->log('meeting.decision_updated', $decision);

        return response()->json($decision->fresh(['assignee', 'instruction', 'structure']));
    }

    public function validateExecution(Request $request, Meeting $meeting, MeetingDecision $decision): JsonResponse
    {
        abort_unless($decision->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canValidateMinutes($request->user(), $meeting) || $this->access->isManager($request->user()), 403);

        $decision->status = MeetingDecisionStatus::Cloturee;
        $decision->execution_validated_by = $request->user()->id;
        $decision->execution_validated_at = now();
        $decision->save();
        $this->audit->log('meeting.decision_execution_validated', $decision);

        return response()->json($decision->fresh(['assignee']));
    }

    public function storeRecommendation(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($this->access->canCreateDecision($request->user(), $meeting), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'agenda_item_id' => ['nullable', 'exists:meeting_agenda_items,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
        ]);

        $reco = $meeting->recommendations()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($reco->load(['structure', 'creator']), 201);
    }

    public function convertRecommendation(Request $request, Meeting $meeting, MeetingRecommendation $recommendation): JsonResponse
    {
        abort_unless($recommendation->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canCreateDecision($request->user(), $meeting), 403);

        $decision = $this->meetings->addDecision($request->user(), $meeting, [
            'title' => $recommendation->title,
            'body' => $recommendation->body,
            'agenda_item_id' => $recommendation->agenda_item_id,
            'structure_id' => $recommendation->structure_id,
            'create_instruction' => $request->boolean('create_instruction'),
            'assignee_id' => $request->input('assignee_id'),
            'due_date' => $request->input('due_date'),
        ]);
        $recommendation->update([
            'status' => 'convertie',
            'converted_decision_id' => $decision->id,
        ]);

        return response()->json($decision, 201);
    }
}
