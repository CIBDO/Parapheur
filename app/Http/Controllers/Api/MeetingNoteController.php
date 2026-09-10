<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Services\AuditLogger;
use App\Services\MeetingAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingNoteController extends Controller
{
    public function __construct(
        private readonly MeetingAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);
        $data = $request->validate([
            'agenda_item_id' => ['nullable', 'exists:meeting_agenda_items,id'],
            'visibility' => ['required', 'in:officielle,privee'],
            'section' => ['nullable', 'in:resume,observations,recommandations,decision'],
            'body' => ['required', 'string'],
        ]);

        if ($data['visibility'] === 'officielle') {
            abort_unless($this->access->canTakeOfficialNotes($request->user(), $meeting), 403, 'Notes officielles réservées au secrétariat de séance.');
        }

        $note = $meeting->sessionNotes()->create([
            ...$data,
            'author_id' => $request->user()->id,
        ]);
        $this->audit->log('meeting.note_created', $meeting, [
            'note_id' => $note->id,
            'visibility' => $note->visibility,
        ]);

        return response()->json($note->load('author'), 201);
    }

    public function update(Request $request, Meeting $meeting, MeetingNote $note): JsonResponse
    {
        abort_unless($note->meeting_id === $meeting->id, 404);
        if ($note->isPrivate()) {
            abort_unless((int) $note->author_id === (int) $request->user()->id, 403);
        } else {
            abort_unless($this->access->canTakeOfficialNotes($request->user(), $meeting), 403);
        }

        $data = $request->validate([
            'body' => ['sometimes', 'string'],
            'section' => ['nullable', 'in:resume,observations,recommandations,decision'],
        ]);
        $note->update($data);

        return response()->json($note->fresh('author'));
    }

    public function destroy(Request $request, Meeting $meeting, MeetingNote $note): JsonResponse
    {
        abort_unless($note->meeting_id === $meeting->id, 404);
        abort_unless((int) $note->author_id === (int) $request->user()->id || $this->access->isManager($request->user()), 403);
        $note->delete();

        return response()->json(['deleted' => true]);
    }
}
