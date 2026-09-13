<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\CirculationSheet;
use App\Models\Correspondence;
use App\Services\CirculationSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CirculationSheetController extends Controller
{
    public function __construct(
        private readonly CirculationSheetService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->list($request->only(['correspondence_id', 'status', 'per_page'])));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'correspondence_id' => 'required|exists:correspondences,id',
            'template_version_id' => 'nullable|exists:document_template_versions,id',
        ]);

        $correspondence = Correspondence::query()->findOrFail($validated['correspondence_id']);
        $this->authorize('view', $correspondence);

        $sheet = $this->service->create($correspondence, $request->user(), $validated);

        return response()->json($sheet, 201);
    }

    public function show(CirculationSheet $circulationSheet): JsonResponse
    {
        $circulationSheet->load(['correspondence', 'document', 'creator', 'templateVersion']);

        return response()->json($circulationSheet);
    }

    public function generateDocument(Request $request, CirculationSheet $circulationSheet): JsonResponse
    {
        try {
            $sheet = $this->service->generateDocument($circulationSheet, $request->user());

            return response()->json([
                'message' => 'Document généré avec succès',
                'circulation_sheet' => $sheet,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Erreur lors de la génération',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function createForCorrespondence(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('view', $correspondence);

        $validated = $request->validate([
            'generate' => 'nullable|boolean',
        ]);

        $sheet = $this->service->create($correspondence, $request->user());

        if ($validated['generate'] ?? false) {
            try {
                $sheet = $this->service->generateDocument($sheet, $request->user());
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'message' => 'Fiche créée mais génération échouée',
                    'error' => $e->getMessage(),
                    'circulation_sheet' => $sheet,
                ], 422);
            }
        }

        return response()->json($sheet, 201);
    }
}
