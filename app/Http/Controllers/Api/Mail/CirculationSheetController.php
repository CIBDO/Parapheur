<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\CirculationSheet;
use App\Models\Correspondence;
use App\Services\CirculationSheetService;
use App\Services\MailSheetPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CirculationSheetController extends Controller
{
    public function __construct(
        private readonly CirculationSheetService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->list($request->user(), $request->only(['correspondence_id', 'status', 'per_page'])));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'correspondence_id' => 'required|exists:correspondences,id',
            'template_version_id' => 'nullable|exists:document_template_versions,id',
            'generate' => 'nullable|boolean',
        ]);

        $correspondence = Correspondence::query()->findOrFail($validated['correspondence_id']);
        $this->authorize('view', $correspondence);

        return $this->respondWithSheet(
            $correspondence,
            $request->user(),
            $validated,
            (bool) ($validated['generate'] ?? false),
        );
    }

    public function show(CirculationSheet $circulationSheet): JsonResponse
    {
        $this->authorize('view', $circulationSheet->correspondence);
        $circulationSheet->load(['correspondence', 'document', 'creator', 'templateVersion']);

        return response()->json($circulationSheet);
    }

    public function generateDocument(Request $request, CirculationSheet $circulationSheet): JsonResponse
    {
        $this->authorize('view', $circulationSheet->correspondence);
        try {
            $sheet = $this->service->generateDocument($circulationSheet, $request->user());

            return response()->json([
                'message' => 'Document généré avec succès',
                'circulation_sheet' => $sheet,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la génération',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function createForCorrespondence(Request $request, Correspondence $correspondence): JsonResponse
    {
        $this->authorize('view', $correspondence);

        if ($request->exists('generate') && ! is_bool($request->input('generate'))) {
            $request->merge([
                'generate' => filter_var($request->input('generate'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        $validated = $request->validate([
            'generate' => 'nullable|boolean',
        ]);

        return $this->respondWithSheet(
            $correspondence,
            $request->user(),
            [],
            (bool) ($validated['generate'] ?? false),
        );
    }

    public function pdf(Request $request, Correspondence $correspondence)
    {
        $this->authorize('view', $correspondence);

        $result = $this->service->findOrCreate($correspondence, $request->user());
        $sheet = $this->service->generateDocument($result['sheet'], $request->user());
        $sheet->load('document.latestVersion');

        $version = $sheet->document?->latestVersion;
        if (! $version) {
            return response()->json(['message' => 'PDF de la fiche indisponible.'], 422);
        }

        $disk = $version->disk ?: 'local';
        $absolute = Storage::disk($disk)->path($version->path);
        $name = MailSheetPdfService::safeFilename(
            'fiche-circulation-'.($sheet->number ?: $correspondence->id).'.pdf'
        );

        return response()->file($absolute, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    private function respondWithSheet(
        Correspondence $correspondence,
        $user,
        array $data,
        bool $generate,
    ): JsonResponse {
        try {
            $result = $this->service->findOrCreate($correspondence, $user, $data);
            $sheet = $result['sheet'];
            $created = $result['created'];

            if ($generate) {
                $sheet = $this->service->generateDocument($sheet, $user);
            }

            $message = match (true) {
                $created && $generate => 'Fiche créée et document généré',
                $created => 'Fiche créée',
                $generate => 'Fiche existante — document généré',
                default => 'Fiche déjà existante pour ce courrier',
            };

            return response()->json([
                'message' => $message,
                'created' => $created,
                'circulation_sheet' => $sheet,
            ], $created ? 201 : 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Impossible de créer la fiche de circulation',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
