<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Models\CorrespondenceAssignment;
use App\Services\CorrespondenceAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceAssignmentController extends Controller
{
    public function __construct(
        private readonly CorrespondenceAssignmentService $assignmentService
    ) {}

    public function store(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('assign', $correspondence);

        $validated = $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.to_user_id' => 'nullable|exists:users,id',
            'assignments.*.to_structure_id' => 'nullable|exists:structures,id',
            'assignments.*.action_id' => 'nullable|exists:correspondence_assignment_actions,id',
            'assignments.*.instruction_text' => 'nullable|string',
            'assignments.*.due_date' => 'nullable|date',
        ]);

        $assignments = $this->assignmentService->assign($correspondence, $request->user(), $validated['assignments']);

        return response()->json($assignments, 201);
    }

    public function takeCharge(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('takeCharge', $correspondence);

        $validated = $request->validate([
            'assignment_id' => 'required|exists:correspondence_assignments,id',
        ]);

        $assignment = CorrespondenceAssignment::query()->findOrFail($validated['assignment_id']);

        if ($assignment->correspondence_id !== $correspondence->id) {
            return response()->json(['message' => 'Cette affectation ne correspond pas à ce courrier.'], 400);
        }

        $updated = $this->assignmentService->takeCharge($assignment, $request->user());

        return response()->json($updated);
    }

    public function reassign(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('assign', $correspondence);

        $validated = $request->validate([
            'assignment_id' => 'required|exists:correspondence_assignments,id',
            'to_user_id' => 'required|exists:users,id',
            'observation' => 'nullable|string',
        ]);

        $assignment = CorrespondenceAssignment::query()->findOrFail($validated['assignment_id']);

        if ($assignment->correspondence_id !== $correspondence->id) {
            return response()->json(['message' => 'Cette affectation ne correspond pas à ce courrier.'], 400);
        }

        $newAssignment = $this->assignmentService->reassign(
            $assignment,
            $request->user(),
            $validated['to_user_id'],
            $validated['observation'] ?? null
        );

        return response()->json($newAssignment);
    }

    public function return(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('takeCharge', $correspondence);

        $validated = $request->validate([
            'assignment_id' => 'required|exists:correspondence_assignments,id',
            'observation' => 'nullable|string',
        ]);

        $assignment = CorrespondenceAssignment::query()->findOrFail($validated['assignment_id']);

        if ($assignment->correspondence_id !== $correspondence->id) {
            return response()->json(['message' => 'Cette affectation ne correspond pas à ce courrier.'], 400);
        }

        $updated = $this->assignmentService->returnAssignment(
            $assignment,
            $request->user(),
            $validated['observation'] ?? null
        );

        return response()->json($updated);
    }

    public function requestComplement(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('process', $correspondence);

        $validated = $request->validate([
            'assignment_id' => 'required|exists:correspondence_assignments,id',
            'observation' => 'required|string|min:3',
        ]);

        $assignment = CorrespondenceAssignment::query()->findOrFail($validated['assignment_id']);

        if ($assignment->correspondence_id !== $correspondence->id) {
            return response()->json(['message' => 'Cette affectation ne correspond pas à ce courrier.'], 400);
        }

        $updated = $this->assignmentService->requestComplement(
            $assignment,
            $request->user(),
            $validated['observation']
        );

        // Notifier le créateur/enregistreur
        if ($correspondence->registeredBy) {
            $correspondence->registeredBy->notify(new \App\Notifications\MailCorrespondenceNotification(
                correspondence: $correspondence,
                event: 'complement_requested',
                message: 'Un complément d\'information a été demandé sur un courrier que vous avez enregistré.',
                actorName: $request->user()->name
            ));
        }

        return response()->json($updated);
    }
}
