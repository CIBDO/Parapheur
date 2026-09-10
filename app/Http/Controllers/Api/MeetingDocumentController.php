<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeetingDocumentKind;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingDocument;
use App\Services\MeetingAccessService;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingDocumentController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly MeetingAccessService $access,
    ) {}

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);
        abort_unless($this->access->canEditPreparation($request->user(), $meeting), 403);

        $data = $request->validate([
            'document_id' => ['required_without:file', 'nullable', 'exists:documents,id'],
            'file' => ['required_without:document_id', 'nullable', 'file', 'max:20480'],
            'kind' => ['nullable', Rule::enum(MeetingDocumentKind::class)],
            'agenda_item_id' => ['nullable', 'exists:meeting_agenda_items,id'],
            'agenda_label' => ['nullable', 'string', 'max:255'],
            'object' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('file')) {
            $link = $this->meetings->uploadDocument($request->user(), $meeting, $request->file('file'), $data);
        } else {
            $link = $this->meetings->attachExistingDocument($request->user(), $meeting, (int) $data['document_id'], $data);
        }

        return response()->json($link, 201);
    }

    public function destroy(Request $request, Meeting $meeting, MeetingDocument $meetingDocument): JsonResponse
    {
        abort_unless($meetingDocument->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canEditPreparation($request->user(), $meeting), 403);
        $meetingDocument->delete();

        return response()->json(['deleted' => true]);
    }
}
