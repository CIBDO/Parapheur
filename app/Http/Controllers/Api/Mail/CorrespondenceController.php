<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Services\CorrespondenceDispatchService;
use App\Services\CorrespondenceReminderService;
use App\Services\CorrespondenceReplyService;
use App\Services\CorrespondenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrespondenceController extends Controller
{
    public function __construct(
        private readonly CorrespondenceService $correspondenceService,
        private readonly CorrespondenceReplyService $replyService,
        private readonly CorrespondenceDispatchService $dispatchService,
        private readonly CorrespondenceReminderService $reminderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Correspondence::class);

        $correspondences = $this->correspondenceService->list(
            $request->user(),
            $request->only([
                'direction', 'status', 'structure_id', 'channel_id', 'search', 'per_page',
                'overdue', 'unassigned', 'mine', 'priority', 'from', 'to',
            ])
        );

        return response()->json($correspondences);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Correspondence::class);

        $validated = $request->validate([
            'q' => 'nullable|string|max:200',
            'direction' => 'nullable|in:entrant,sortant,interne',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $results = $this->correspondenceService->list($request->user(), [
            'search' => $validated['q'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'status' => $validated['status'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'per_page' => $validated['per_page'] ?? 30,
        ]);

        return response()->json($results);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Correspondence::class);

        $this->normalizeBooleanInputs($request, ['requires_reply']);

        $validated = $request->validate([
            'direction' => 'required|in:entrant,sortant,interne',
            'subject' => 'required|string|max:500',
            'summary' => 'nullable|string',
            'observations' => 'nullable|string',
            'external_reference' => 'nullable|string|max:200',
            'correspondence_date' => 'nullable|date',
            'received_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'medium' => 'required|in:physique,electronique,hybride',
            'priority' => 'nullable|string',
            'confidentiality' => 'nullable|string',
            'structure_id' => 'nullable|exists:structures,id',
            'channel_id' => 'nullable|exists:correspondence_channels,id',
            'category_id' => 'nullable|exists:correspondence_categories,id',
            'qualification_id' => 'nullable|exists:correspondence_qualifications,id',
            'piece_count' => 'nullable|integer|min:1',
            'keywords' => 'nullable|array',
            'requires_reply' => 'nullable|boolean',
            'scan_file' => 'nullable|file|max:20480',
            'document_data' => 'nullable|array',
            'parties' => 'nullable|array',
        ]);

        $direction = $validated['direction'];
        $scanFile = $request->file('scan_file');
        unset($validated['direction'], $validated['scan_file'], $validated['document_data'], $validated['parties']);

        $correspondence = match ($direction) {
            'entrant' => $this->correspondenceService->createIncoming($request->user(), $validated, $scanFile),
            'sortant' => $this->correspondenceService->createOutgoing($request->user(), $validated),
            'interne' => $this->correspondenceService->createInternal($request->user(), $validated),
        };

        return response()->json($this->correspondenceService->serialize($correspondence), 201);
    }

    public function show(Correspondence $correspondence): JsonResponse
    {
        $this->authorize('view', $correspondence);

        return response()->json($this->correspondenceService->serialize($correspondence));
    }

    public function update(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        $this->normalizeBooleanInputs($request, ['requires_reply']);

        $validated = $request->validate([
            'subject' => 'sometimes|string|max:500',
            'summary' => 'nullable|string',
            'observations' => 'nullable|string',
            'external_reference' => 'nullable|string|max:200',
            'correspondence_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'medium' => 'sometimes|in:physique,electronique,hybride',
            'priority' => 'nullable|string',
            'confidentiality' => 'nullable|string',
            'channel_id' => 'nullable|exists:correspondence_channels,id',
            'category_id' => 'nullable|exists:correspondence_categories,id',
            'qualification_id' => 'nullable|exists:correspondence_qualifications,id',
            'piece_count' => 'nullable|integer|min:1',
            'keywords' => 'nullable|array',
            'requires_reply' => 'nullable|boolean',
        ]);

        $updated = $this->correspondenceService->update($correspondence, $request->user(), $validated);

        return response()->json($this->correspondenceService->serialize($updated));
    }

    public function destroy(Correspondence $correspondence): JsonResponse
    {
        $this->authorize('delete', $correspondence);
        $correspondence->delete();

        return response()->json(['message' => 'Courrier supprimé']);
    }

    public function history(Correspondence $correspondence): JsonResponse
    {
        $this->authorize('view', $correspondence);

        return response()->json($correspondence->events()->with('user')->orderByDesc('created_at')->get());
    }

    public function register(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        if ($correspondence->direction?->value === 'sortant' && ! $correspondence->departure_number) {
            $correspondence = $this->replyService->assignDepartureNumber($correspondence, $request->user());
        }

        return response()->json($this->correspondenceService->serialize($correspondence));
    }

    public function reply(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('reply', $correspondence);

        $validated = $request->validate([
            'subject' => 'nullable|string|max:500',
            'summary' => 'nullable|string',
            'medium' => 'nullable|in:physique,electronique,hybride',
            'priority' => 'nullable|string',
            'confidentiality' => 'nullable|string',
        ]);

        $reply = $this->replyService->prepareReply($correspondence, $request->user(), $validated);

        return response()->json($this->correspondenceService->serialize($reply), 201);
    }

    public function submitToParapheur(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        $validated = $request->validate([
            'to_user_id' => 'nullable|exists:users,id',
            'workflow_id' => 'nullable|exists:workflows,id',
            'expected_action' => 'nullable|string',
            'message' => 'nullable|string',
            'assignees' => 'nullable|array',
        ]);

        $updated = $this->replyService->submitToParapheur($correspondence, $request->user(), $validated);

        return response()->json($this->correspondenceService->serialize($updated));
    }

    public function dispatch(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('dispatch', $correspondence);

        $validated = $request->validate([
            'method' => 'required|string|max:50',
            'tracking_number' => 'nullable|string|max:100',
            'dispatched_at' => 'nullable|date',
            'observations' => 'nullable|string',
        ]);

        $dispatch = $this->dispatchService->recordDispatch($correspondence, $request->user(), $validated);

        return response()->json([
            'dispatch' => $dispatch,
            'correspondence' => $this->correspondenceService->serialize($correspondence->fresh()),
        ], 201);
    }

    public function acknowledge(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        $validated = $request->validate([
            'acknowledged_by_name' => 'nullable|string|max:200',
            'method' => 'nullable|string|max:50',
            'acknowledged_at' => 'nullable|date',
            'observations' => 'nullable|string',
            'proof' => 'nullable|file|max:20480',
        ]);

        $ack = $this->dispatchService->recordAcknowledgement(
            $correspondence,
            $request->user(),
            $validated,
            $request->file('proof')
        );

        return response()->json([
            'acknowledgement' => $ack,
            'correspondence' => $this->correspondenceService->serialize($correspondence->fresh()),
        ], 201);
    }

    public function syncParties(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        $validated = $request->validate([
            'parties' => 'required|array',
            'parties.*.role' => 'required|in:from,to,cc,ampliation,info',
            'parties.*.name' => 'nullable|string|max:200',
            'parties.*.organization' => 'nullable|string|max:200',
            'parties.*.correspondent_id' => 'nullable|exists:correspondents,id',
            'parties.*.function' => 'nullable|string|max:200',
        ]);

        $this->correspondenceService->syncParties($correspondence, $request->user(), $validated['parties']);

        return response()->json($this->correspondenceService->serialize($correspondence->fresh()));
    }

    public function archive(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('archive', $correspondence);

        $updated = $this->dispatchService->archive($correspondence, $request->user());

        return response()->json($this->correspondenceService->serialize($updated));
    }

    public function printDocument(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('view', $correspondence);

        if (! $correspondence->document_id) {
            return response()->json(['message' => 'Aucun document à imprimer'], 422);
        }

        $validated = $request->validate([
            'copies' => 'nullable|integer|min:1|max:10',
            'reason' => 'nullable|string|max:255',
            'is_reprint' => 'nullable|boolean',
        ]);

        \App\Models\DocumentPrintLog::query()->create([
            'document_id' => $correspondence->document_id,
            'correspondence_id' => $correspondence->id,
            'user_id' => $request->user()->id,
            'page_count' => $validated['copies'] ?? 1,
            'reason' => $validated['reason'] ?? null,
            'is_reprint' => $validated['is_reprint'] ?? false,
        ]);

        return response()->json([
            'message' => 'Impression enregistrée',
            'document_id' => $correspondence->document_id,
        ]);
    }

    public function attachSignedVersion(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        $validated = $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,docx',
            'note' => 'nullable|string|max:500',
        ]);

        if (! $correspondence->document_id) {
            return response()->json(['message' => 'Aucun document principal associé'], 422);
        }

        $document = $correspondence->document;
        $documentService = app(\App\Services\DocumentService::class);

        // Ajouter la version signée
        $documentService->addVersion(
            $document,
            $request->user(),
            $request->file('file'),
            $validated['note'] ?? 'Version signée physiquement'
        );

        // Mettre à jour le statut de la correspondance si possible
        $stateMachine = app(\App\Services\CorrespondenceStateMachine::class);
        if ($stateMachine->canTransition($correspondence->status, \App\Enums\CorrespondenceStatus::Signe)) {
            $oldStatus = $correspondence->status->value;
            $correspondence->status = \App\Enums\CorrespondenceStatus::Signe;
            $correspondence->save();

            $eventService = app(\App\Services\CorrespondenceEventService::class);
            $eventService->logStatusTransition($correspondence, $oldStatus, \App\Enums\CorrespondenceStatus::Signe->value, $request->user());
        }

        return response()->json([
            'message' => 'Version signée ajoutée avec succès',
            'correspondence' => $this->correspondenceService->serialize($correspondence->fresh()),
        ]);
    }

    public function reminders(Correspondence $correspondence): JsonResponse
    {
        $this->authorize('view', $correspondence);

        return response()->json($this->reminderService->list($correspondence));
    }

    public function storeReminder(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        $validated = $request->validate([
            'reminder_date' => 'required|date',
            'type' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:1000',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $reminder = $this->reminderService->createManual($correspondence, $request->user(), $validated);

        return response()->json($reminder, 201);
    }

    public function scheduleReminders(Correspondence $correspondence): JsonResponse
    {
        $this->authorize('update', $correspondence);

        $count = $this->reminderService->scheduleAutomatic($correspondence);

        return response()->json([
            'message' => 'Relances planifiées',
            'created' => $count,
            'reminders' => $this->reminderService->list($correspondence),
        ]);
    }

    /**
     * FormData envoie les booléens comme "true"/"false", non acceptés par la règle Laravel `boolean`.
     *
     * @param  list<string>  $keys
     */
    private function normalizeBooleanInputs(Request $request, array $keys): void
    {
        $merged = [];

        foreach ($keys as $key) {
            if (! $request->exists($key)) {
                continue;
            }

            $value = $request->input($key);
            if (is_bool($value)) {
                continue;
            }

            $merged[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($merged !== []) {
            $request->merge($merged);
        }
    }
}
