<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Instruction;
use App\Models\Meeting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Meeting::query()->with(['chair', 'creator', 'participants', 'documents', 'decisions'])->orderByDesc('meeting_date')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'chair_id' => ['nullable', 'exists:users,id'],
            'agenda' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['exists:users,id'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['exists:documents,id'],
        ]);

        $meeting = Meeting::query()->create([
            ...collect($data)->except(['participant_ids', 'document_ids'])->all(),
            'created_by' => $request->user()->id,
        ]);

        if (! empty($data['participant_ids'])) {
            $meeting->participants()->sync($data['participant_ids']);
        }

        if (! empty($data['document_ids'])) {
            $sync = [];
            foreach ($data['document_ids'] as $i => $id) {
                $sync[$id] = ['sort_order' => $i + 1];
            }
            $meeting->documents()->sync($sync);
        }

        $this->audit->log('meeting.created', $meeting);

        return response()->json($meeting->load(['chair', 'participants', 'documents']), 201);
    }

    public function show(Meeting $meeting): JsonResponse
    {
        return response()->json(
            $meeting->load(['chair', 'creator', 'participants', 'documents.type', 'decisions.assignee', 'decisions.instruction'])
        );
    }

    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'meeting_date' => ['sometimes', 'date'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'chair_id' => ['nullable', 'exists:users,id'],
            'agenda' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['exists:users,id'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['exists:documents,id'],
        ]);

        $meeting->update(collect($data)->except(['participant_ids', 'document_ids'])->all());

        if (array_key_exists('participant_ids', $data)) {
            $meeting->participants()->sync($data['participant_ids'] ?? []);
        }

        if (array_key_exists('document_ids', $data)) {
            $sync = [];
            foreach ($data['document_ids'] ?? [] as $i => $id) {
                $sync[$id] = ['sort_order' => $i + 1];
            }
            $meeting->documents()->sync($sync);
        }

        $this->audit->log('meeting.updated', $meeting);

        return response()->json($meeting->load(['chair', 'participants', 'documents', 'decisions.assignee']));
    }

    public function addDecision(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'due_date' => ['nullable', 'date'],
            'create_instruction' => ['nullable', 'boolean'],
        ]);

        $decision = $meeting->decisions()->create(collect($data)->except('create_instruction')->all());

        if ($request->boolean('create_instruction') && ! empty($data['assignee_id'])) {
            Instruction::query()->create([
                'meeting_decision_id' => $decision->id,
                'issuer_id' => $request->user()->id,
                'assignee_id' => $data['assignee_id'],
                'structure_id' => $data['structure_id'] ?? null,
                'title' => $data['title'],
                'body' => $data['body'] ?? $data['title'],
                'status' => 'a_faire',
                'due_date' => $data['due_date'] ?? null,
            ]);
        }

        $this->audit->log('meeting.decision_created', $decision);

        return response()->json($decision->load(['assignee', 'instruction']), 201);
    }
}
