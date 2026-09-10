<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Services\MeetingAccessService;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingMinutesController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly MeetingAccessService $access,
    ) {}

    public function generateConvocation(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $meeting);
        $result = $this->meetings->generateConvocation($request->user(), $meeting);

        return response()->json([
            'html' => $result['html'],
            'document' => $result['document'],
            'meeting' => $this->meetings->serialize($meeting->fresh(), $request->user()),
        ]);
    }

    public function submitConvocation(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'validator_id' => ['nullable', 'exists:users,id'],
        ]);
        $meeting = $this->meetings->submitConvocation($request->user(), $meeting, $data['validator_id'] ?? null);

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }

    public function sendInvitations(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless(
            $this->access->isManager($request->user())
            || $this->access->canValidateMinutes($request->user(), $meeting)
            || $this->access->isOfficer($request->user(), $meeting),
            403
        );

        $meeting = $this->meetings->validateAndSendInvitations($request->user(), $meeting);

        return response()->json($this->meetings->serialize($meeting, $request->user()));
    }

    public function generate(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($this->access->canTakeOfficialNotes($request->user(), $meeting) || $this->access->isManager($request->user()), 403);
        $data = $request->validate([
            'kind' => ['nullable', Rule::in(['cr_simple', 'cr_detaille', 'pv', 'releve_decisions'])],
        ]);

        $minute = $this->meetings->generateMinutes($request->user(), $meeting, $data['kind'] ?? 'cr_detaille');

        return response()->json($minute, 201);
    }

    public function update(Request $request, Meeting $meeting, MeetingMinute $minute): JsonResponse
    {
        abort_unless($minute->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canTakeOfficialNotes($request->user(), $meeting) || $this->access->isManager($request->user()), 403);
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);
        abort_unless(in_array($minute->status, ['brouillon', 'soumis'], true), 422, 'Ce compte rendu n’est plus modifiable.');
        $minute->update($data);

        return response()->json($minute);
    }

    public function submit(Request $request, Meeting $meeting, MeetingMinute $minute): JsonResponse
    {
        abort_unless($minute->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canTakeOfficialNotes($request->user(), $meeting) || $this->access->isManager($request->user()), 403);

        return response()->json($this->meetings->submitMinutes($request->user(), $meeting, $minute));
    }

    public function validateMinute(Request $request, Meeting $meeting, MeetingMinute $minute): JsonResponse
    {
        abort_unless($minute->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canValidateMinutes($request->user(), $meeting), 403);

        return response()->json($this->meetings->validateMinutes($request->user(), $meeting, $minute));
    }

    public function diffuse(Request $request, Meeting $meeting, MeetingMinute $minute): JsonResponse
    {
        abort_unless($minute->meeting_id === $meeting->id, 404);
        abort_unless($this->access->canValidateMinutes($request->user(), $meeting) || $this->access->isManager($request->user()), 403);

        return response()->json($this->meetings->diffuseMinutes($request->user(), $meeting, $minute));
    }

    public function preview(Request $request, Meeting $meeting, MeetingMinute $minute)
    {
        abort_unless($minute->meeting_id === $meeting->id, 404);
        $this->access->authorizeView($request->user(), $meeting);

        return response($minute->body ?: '', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
