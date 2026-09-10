<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Services\MeetingAccessService;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingParticipantController extends Controller
{
    public function __construct(
        private readonly MeetingService $meetings,
        private readonly MeetingAccessService $access,
    ) {}

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $meeting);

        $type = $request->input('participation_type', $request->filled('user_id') ? 'interne' : 'externe');

        $data = $request->validate([
            'participation_type' => ['nullable', 'in:interne,externe'],
            'user_id' => [
                Rule::requiredIf($type === 'interne'),
                'nullable',
                'exists:users,id',
            ],
            'role' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'external_name' => [
                Rule::requiredIf($type === 'externe'),
                'nullable',
                'string',
                'max:255',
            ],
            'external_function' => ['nullable', 'string', 'max:255'],
            'external_structure' => ['nullable', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf($type === 'externe'),
                'nullable',
                'email',
                'max:255',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'observations' => ['nullable', 'string'],
        ]);

        $data['participation_type'] = $type;

        if ($type === 'interne') {
            $data['external_name'] = null;
            $data['external_function'] = null;
            $data['external_structure'] = null;
            abort_if(
                $meeting->participants()->where('user_id', $data['user_id'])->exists(),
                422,
                'Ce participant interne est déjà inscrit à la réunion.'
            );
        } else {
            $data['user_id'] = null;
            abort_if(
                $meeting->participants()->where('email', $data['email'])->exists(),
                422,
                'Un invité externe avec cet e-mail est déjà inscrit.'
            );
        }

        return response()->json($this->meetings->addParticipant($meeting, $data), 201);
    }

    public function destroy(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        abort_unless($participant->meeting_id === $meeting->id, 404);
        $this->access->authorizeManage($request->user(), $meeting);
        $participant->delete();

        return response()->json(['deleted' => true]);
    }

    public function confirm(Request $request, Meeting $meeting): JsonResponse
    {
        $this->access->authorizeView($request->user(), $meeting);
        $data = $request->validate([
            'status' => ['required', 'in:confirme,refuse,absent,excuse,represente'],
            'representative_id' => ['nullable', 'exists:users,id'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ]);

        $participant = $meeting->participants()->where('user_id', $request->user()->id)->firstOrFail();
        if ($data['status'] === 'represente') {
            abort_unless(! empty($data['representative_id']) || ! empty($data['representative_name']), 422, 'Indiquez le représentant.');
        }

        return response()->json($this->meetings->confirmParticipation($request->user(), $participant, $data['status'], $data));
    }

    public function attendance(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        abort_unless($participant->meeting_id === $meeting->id, 404);
        abort_unless(
            $this->access->canStart($request->user(), $meeting)
            || $request->user()->can('meetings.manage_attendance'),
            403
        );
        $data = $request->validate([
            'attendance_status' => ['required', 'in:present,absent,excuse,represente,invite'],
            'arrived_at' => ['nullable', 'date'],
            'left_at' => ['nullable', 'date'],
            'representative_id' => ['nullable', 'exists:users,id'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ]);

        return response()->json($this->meetings->recordAttendance($meeting, $participant, $data));
    }
}
