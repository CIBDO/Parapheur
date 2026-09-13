<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Enums\ExpectedAction;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocumentLink;
use App\Services\DocumentBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class WorkspaceBridgeController extends Controller
{
    public function __construct(
        private readonly DocumentBridgeService $bridge,
    ) {}

    public function submitToGed(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('edit', $workspace);
        $this->assertLinked($workspace, $document);

        $data = $request->validate([
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
            'detach_link' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->bridge->submitToGed(
                $document,
                $request->user(),
                $data['classification_node_id'] ?? null,
            );
            if (! empty($data['detach_link'])) {
                $this->bridge->detachFromWorkspaceAfterGedSubmit($result, false);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function submitToParapheur(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('edit', $workspace);
        $this->assertLinked($workspace, $document);

        $data = $request->validate([
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'expected_action' => ['nullable', Rule::in(array_column(ExpectedAction::cases(), 'value'))],
            'message' => ['nullable', 'string', 'max:2000'],
            'object' => ['nullable', 'string', 'max:500'],
            'priority' => ['nullable', 'string'],
            'confidentiality' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (empty($data['workflow_id']) && empty($data['recipient_ids'])) {
            return response()->json(['message' => 'Indiquez un destinataire ou un circuit.'], 422);
        }

        $recipients = User::query()->whereIn('id', $data['recipient_ids'] ?? [])->get()->all();
        $action = ExpectedAction::tryFrom($data['expected_action'] ?? '') ?? ExpectedAction::Visa;

        try {
            $result = $this->bridge->submitToParapheur(
                $document,
                $request->user(),
                $recipients,
                $action,
                $data['message'] ?? null,
                $data['workflow_id'] ?? null,
                $data['object'] ?? null,
                $data['priority'] ?? null,
                $data['confidentiality'] ?? null,
                $data['due_date'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function workingCopy(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('contribute', $workspace);

        $data = $request->validate([
            'folder_id' => ['nullable', 'exists:workspace_folders,id'],
        ]);

        try {
            $copy = $this->bridge->createWorkingCopy(
                $document,
                $request->user(),
                $workspace->id,
                $data['folder_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($copy, 201);
    }

    public function attachMeeting(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('view', $workspace);
        $this->assertLinked($workspace, $document);

        $data = $request->validate([
            'meeting_id' => ['required', 'exists:meetings,id'],
            'kind' => ['nullable', 'string'],
            'agenda_item_id' => ['nullable', 'integer'],
        ]);

        try {
            $link = $this->bridge->attachToMeeting(
                $document,
                $request->user(),
                Meeting::query()->findOrFail($data['meeting_id']),
                $data,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($link, 201);
    }

    public function attachAppointment(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('view', $workspace);
        $this->assertLinked($workspace, $document);

        $data = $request->validate([
            'appointment_id' => ['required', 'exists:appointments,id'],
            'kind' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $link = $this->bridge->attachToAppointment(
                $document,
                $request->user(),
                Appointment::query()->findOrFail($data['appointment_id']),
                $data,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($link, 201);
    }

    public function attachInstruction(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('view', $workspace);
        $this->assertLinked($workspace, $document);

        $data = $request->validate([
            'instruction_id' => ['required', 'exists:instructions,id'],
            'as_primary' => ['nullable', 'boolean'],
        ]);

        try {
            $instruction = $this->bridge->attachToInstruction(
                $document,
                $request->user(),
                Instruction::query()->findOrFail($data['instruction_id']),
                (bool) ($data['as_primary'] ?? false),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($instruction);
    }

    private function assertLinked(Workspace $workspace, Document $document): void
    {
        $exists = WorkspaceDocumentLink::query()
            ->where('workspace_id', $workspace->id)
            ->where('document_id', $document->id)
            ->exists();

        abort_unless($exists, 404, 'Document non présent dans cet espace.');
    }
}
