<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductionOccurrenceStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductionFile;
use App\Models\ProductionOccurrence;
use App\Services\Productions\DeliverableService;
use App\Services\Productions\ProductionOccurrenceService;
use App\Services\Productions\ProductionTransmissionService;
use App\Services\Productions\ProductionValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionOccurrenceController extends Controller
{
    public function __construct(
        private readonly ProductionOccurrenceService $occurrences,
        private readonly DeliverableService $deliverables,
        private readonly ProductionValidationService $validations,
        private readonly ProductionTransmissionService $transmissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductionOccurrence::class);

        return response()->json($this->occurrences->list($request->user(), $request->all()));
    }

    public function show(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('view', $occurrence);

        $loaded = $this->occurrences->find($request->user(), $occurrence);
        $this->occurrences->recordConsultation($request->user(), $loaded);
        $loaded = $this->occurrences->find($request->user(), $occurrence);
        $payload = $loaded->toArray();
        $payload['delay_flags'] = $loaded->delayFlags();

        return response()->json($payload);
    }

    public function deposit(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $this->deliverables->deposit(
            $request->user(),
            $occurrence,
            $request->file('file'),
            $request->input('description')
        );

        return response()->json($file, 201);
    }

    public function replaceFile(Request $request, ProductionOccurrence $occurrence, ProductionFile $file): JsonResponse
    {
        $this->authorize('update', $occurrence);
        abort_unless((int) $file->production_occurrence_id === (int) $occurrence->id, 404);
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(
            $this->deliverables->replaceVersion(
                $request->user(),
                $file,
                $request->file('file'),
                $request->input('description')
            )
        );
    }

    public function createOffice(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', Rule::in(['docx', 'xlsx', 'pptx'])],
        ]);

        return response()->json(
            $this->deliverables->createOffice($request->user(), $occurrence, $data),
            201
        );
    }

    public function submitGed(Request $request, ProductionOccurrence $occurrence, ProductionFile $file): JsonResponse
    {
        $this->authorize('update', $occurrence);
        abort_unless((int) $file->production_occurrence_id === (int) $occurrence->id, 404);
        $data = $request->validate([
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
        ]);

        return response()->json(
            $this->deliverables->classifyInGed(
                $request->user(),
                $file,
                isset($data['classification_node_id']) ? (int) $data['classification_node_id'] : null
            )
        );
    }

    public function submitParapheur(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);
        $data = $request->validate([
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
        ]);

        return response()->json(
            $this->deliverables->submitToParapheur(
                $request->user(),
                $occurrence,
                $data['recipient_ids'] ?? []
            )
        );
    }

    public function removeFile(Request $request, ProductionOccurrence $occurrence, ProductionFile $file): JsonResponse
    {
        $this->authorize('update', $occurrence);
        abort_unless((int) $file->production_occurrence_id === (int) $occurrence->id, 404);
        $this->deliverables->remove($request->user(), $file);

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);

        return response()->json($this->validations->submit($request->user(), $occurrence));
    }

    public function decide(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('validate', $occurrence);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'correction'])],
            'motif' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->validations->decide(
                $request->user(),
                $occurrence,
                $data['decision'],
                $data['motif'] ?? null,
                $data['comment'] ?? null
            )
        );
    }

    public function transmit(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('transmit', $occurrence);
        $data = $request->validate([
            'rectificative' => ['nullable', 'boolean'],
            'previous_transmission_id' => ['nullable', 'exists:production_transmissions,id'],
        ]);

        return response()->json(
            $this->transmissions->transmit(
                $request->user(),
                $occurrence,
                (bool) ($data['rectificative'] ?? false),
                $data['previous_transmission_id'] ?? null
            )
        );
    }

    public function justifyDelay(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);
        $data = $request->validate([
            'justification' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json(
            $this->occurrences->justifyDelay($request->user(), $occurrence, $data['justification'])
        );
    }

    public function updateStatus(Request $request, ProductionOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);
        $data = $request->validate([
            'status' => ['required', Rule::in(ProductionOccurrenceStatus::values())],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->occurrences->updateStatus(
                $request->user(),
                $occurrence,
                ProductionOccurrenceStatus::from($data['status']),
                $data['notes'] ?? null
            )
        );
    }
}
