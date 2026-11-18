<?php

namespace App\Http\Controllers\Api\Archives;

use App\Http\Controllers\Controller;
use App\Models\ArchiveTransfer;
use App\Services\Archives\ArchiveIngestService;
use App\Services\Archives\ArchiveSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ArchiveTransferController extends Controller
{
    public function __construct(
        private readonly ArchiveSearchService $search,
        private readonly ArchiveIngestService $ingest,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchiveTransfer::class);

        $page = $this->search->searchTransfers(
            $request->user(),
            $request->only(['status', 'mine', 'to_control', 'q']),
            (int) $request->integer('per_page', 15)
        );

        return response()->json($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ArchiveTransfer::class);

        $data = $request->validate([
            'structure_id' => ['required', 'integer', 'exists:structures,id'],
            'producer_unit_id' => ['nullable', 'integer', 'exists:structures,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'typology' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'source_module' => ['nullable', 'string', 'max:64'],
            'source_ref' => ['nullable', 'string', 'max:128'],
            'medium' => ['nullable', 'string', 'in:electronique,physique,hybride'],
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ]);

        $transfer = $this->ingest->createTransfer($request->user(), $data);

        return response()->json($transfer, 201);
    }

    public function show(Request $request, ArchiveTransfer $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);

        $transfer->load(['items.document.latestVersion', 'items.documentVersion', 'structure', 'producerUnit', 'creator', 'records']);

        return response()->json($transfer);
    }

    public function submit(Request $request, ArchiveTransfer $transfer): JsonResponse
    {
        $this->authorize('submit', $transfer);

        try {
            return response()->json($this->ingest->submit($transfer, $request->user()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function control(Request $request, ArchiveTransfer $transfer): JsonResponse
    {
        $this->authorize('control', $transfer);

        try {
            return response()->json($this->ingest->startControl($transfer, $request->user()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function accept(Request $request, ArchiveTransfer $transfer): JsonResponse
    {
        $this->authorize('accept', $transfer);

        try {
            return response()->json($this->ingest->accept($transfer, $request->user()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, ArchiveTransfer $transfer): JsonResponse
    {
        $this->authorize('reject', $transfer);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            return response()->json($this->ingest->reject($transfer, $request->user(), $data['reason']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function takeOver(Request $request, ArchiveTransfer $transfer): JsonResponse
    {
        $this->authorize('takeOver', $transfer);

        try {
            return response()->json($this->ingest->takeOver($transfer, $request->user()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
