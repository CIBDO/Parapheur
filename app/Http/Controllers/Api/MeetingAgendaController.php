<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Services\MeetingAccessService;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingAgendaController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly MeetingAccessService $access,
    ) {}

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);
        abort_unless($this->access->canManageAgenda($request->user(), $meeting), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'presenter_id' => ['nullable', 'exists:users,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'sort_order' => ['nullable', 'integer'],
            'confidentiality' => ['nullable', 'string'],
            'preparatory_notes' => ['nullable', 'string'],
            'is_follow_up' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->meetings->addAgendaItem($meeting, $data), 201);
    }

    public function update(Request $request, Meeting $meeting, MeetingAgendaItem $agendaItem): JsonResponse
    {
        abort_unless($agendaItem->meeting_id === $meeting->id, 404);
        $this->access->authorizeView($request->user(), $meeting);
        abort_unless($this->access->canManageAgenda($request->user(), $meeting), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'presenter_id' => ['nullable', 'exists:users,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'sort_order' => ['nullable', 'integer'],
            'confidentiality' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'preparatory_notes' => ['nullable', 'string'],
        ]);

        $agendaItem->update($data);

        return response()->json($agendaItem->fresh('presenter'));
    }

    public function destroy(Request $request, Meeting $meeting, MeetingAgendaItem $agendaItem): JsonResponse
    {
        abort_unless($agendaItem->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canManageAgenda($request->user(), $meeting), 403);
        $agendaItem->delete();

        return response()->json(['deleted' => true]);
    }

    public function reorder(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($this->access->canManageAgenda($request->user(), $meeting), 403);
        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);
        $this->meetings->reorderAgenda($meeting, $data['ordered_ids']);

        return response()->json($meeting->agendaItems()->with('presenter')->orderBy('sort_order')->get());
    }

    public function setCurrent(Request $request, Meeting $meeting, MeetingAgendaItem $agendaItem): JsonResponse
    {
        abort_unless($agendaItem->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canStart($request->user(), $meeting), 403);
        $meeting->current_agenda_item_id = $agendaItem->id;
        $meeting->save();

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }
}
