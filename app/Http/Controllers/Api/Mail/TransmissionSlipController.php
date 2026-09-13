<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\DocumentPrintLog;
use App\Models\TransmissionSlip;
use App\Services\TransmissionSlipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransmissionSlipController extends Controller
{
    public function __construct(
        private readonly TransmissionSlipService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransmissionSlip::class);

        $query = TransmissionSlip::query()->with(['fromStructure', 'toStructure', 'creator', 'items.correspondence']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TransmissionSlip::class);

        $validated = $request->validate([
            'from_structure_id' => 'nullable|exists:structures,id',
            'to_structure_id' => 'nullable|exists:structures,id',
            'nature' => 'nullable|string|max:100',
            'observations' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.correspondence_id' => 'nullable|exists:correspondences,id',
            'items.*.reference' => 'nullable|string',
            'items.*.object' => 'nullable|string',
            'items.*.piece_count' => 'nullable|integer|min:1',
            'items.*.observations' => 'nullable|string',
        ]);

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $slip = $this->service->create($validated, $items);

        return response()->json($slip, 201);
    }

    public function show(TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('view', $transmissionSlip);

        return response()->json($transmissionSlip->load([
            'fromStructure', 'toStructure', 'creator', 'validator', 'document', 'items.correspondence',
        ]));
    }

    public function update(Request $request, TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('update', $transmissionSlip);

        $validated = $request->validate([
            'from_structure_id' => 'nullable|exists:structures,id',
            'to_structure_id' => 'nullable|exists:structures,id',
            'nature' => 'nullable|string|max:100',
            'observations' => 'nullable|string',
        ]);

        $transmissionSlip->update($validated);

        return response()->json($transmissionSlip->fresh(['fromStructure', 'toStructure', 'items']));
    }

    public function destroy(TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('delete', $transmissionSlip);
        $transmissionSlip->delete();

        return response()->json(['message' => 'Bordereau supprimé']);
    }

    public function addItems(Request $request, TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('update', $transmissionSlip);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.correspondence_id' => 'nullable|exists:correspondences,id',
            'items.*.reference' => 'nullable|string',
            'items.*.object' => 'nullable|string',
            'items.*.piece_count' => 'nullable|integer|min:1',
        ]);

        return response()->json($this->service->addItems($transmissionSlip, $validated['items']));
    }

    public function removeItem(TransmissionSlip $transmissionSlip, int $itemId): JsonResponse
    {
        $this->authorize('update', $transmissionSlip);

        return response()->json($this->service->removeItem($transmissionSlip, $itemId));
    }

    public function generateDocument(TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('update', $transmissionSlip);

        try {
            return response()->json($this->service->generateDocument($transmissionSlip));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function validate(TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('validate', $transmissionSlip);

        return response()->json($this->service->validate($transmissionSlip));
    }

    public function print(Request $request, TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('print', $transmissionSlip);

        $validated = $request->validate([
            'copies' => 'nullable|integer|min:1|max:10',
            'reason' => 'nullable|string|max:255',
            'is_reprint' => 'nullable|boolean',
        ]);

        $slip = $this->service->markPrinted($transmissionSlip);

        DocumentPrintLog::query()->create([
            'document_id' => $slip->document_id,
            'transmission_slip_id' => $slip->id,
            'user_id' => $request->user()->id,
            'page_count' => $validated['copies'] ?? 1,
            'reason' => $validated['reason'] ?? null,
            'is_reprint' => $validated['is_reprint'] ?? false,
        ]);

        return response()->json($slip);
    }

    public function send(TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('update', $transmissionSlip);

        return response()->json($this->service->send($transmissionSlip));
    }

    public function acknowledge(Request $request, TransmissionSlip $transmissionSlip): JsonResponse
    {
        $this->authorize('receive', $transmissionSlip);

        $validated = $request->validate([
            'received_at' => 'nullable|date',
            'observations' => 'nullable|string',
            'proof' => 'nullable|file|max:20480',
        ]);

        $proofFile = $request->file('proof');

        return response()->json($this->service->acknowledge(
            $transmissionSlip,
            $validated,
            $proofFile,
            $request->user()
        ));
    }

    public function printLogs(Request $request): JsonResponse
    {
        $query = DocumentPrintLog::query()->with(['document', 'user', 'correspondence', 'transmissionSlip']);

        if ($request->filled('correspondence_id')) {
            $query->where('correspondence_id', $request->integer('correspondence_id'));
        }

        if ($request->filled('transmission_slip_id')) {
            $query->where('transmission_slip_id', $request->integer('transmission_slip_id'));
        }

        if ($request->filled('document_id')) {
            $query->where('document_id', $request->integer('document_id'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->integer('per_page', 20)));
    }
}
