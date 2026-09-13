<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Services\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentTemplate::class);

        $query = DocumentTemplate::query()->with(['currentPublishedVersion', 'structure']);

        if ($request->filled('kind')) {
            $query->where('kind', $request->string('kind'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DocumentTemplate::class);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:document_templates,code',
            'name' => 'required|string|max:255',
            'kind' => 'required|string',
            'structure_id' => 'nullable|exists:structures,id',
            'confidentiality' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'template_file' => 'nullable|file|mimes:doc,docx|max:20480',
            'change_note' => 'nullable|string',
        ]);

        $template = $this->service->create($request->user(), $validated, $request->file('template_file'));

        return response()->json($template, 201);
    }

    public function show(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('view', $documentTemplate);

        return response()->json($documentTemplate->load(['versions.creator', 'currentPublishedVersion', 'structure']));
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('update', $documentTemplate);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'kind' => 'sometimes|string',
            'structure_id' => 'nullable|exists:structures,id',
            'confidentiality' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $documentTemplate->update($validated);

        return response()->json($documentTemplate->fresh(['currentPublishedVersion']));
    }

    public function destroy(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('delete', $documentTemplate);
        $this->service->delete($documentTemplate);

        return response()->json(['message' => 'Modèle supprimé']);
    }

    public function createVersion(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('update', $documentTemplate);

        $validated = $request->validate([
            'template_file' => 'required|file|mimes:doc,docx|max:20480',
            'change_note' => 'nullable|string',
        ]);

        $version = $this->service->addVersion(
            $documentTemplate,
            $request->user(),
            $request->file('template_file'),
            $validated['change_note'] ?? null
        );

        return response()->json($version, 201);
    }

    public function publish(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('publish', $documentTemplate);

        $validated = $request->validate([
            'version_id' => 'required|exists:document_template_versions,id',
        ]);

        $version = DocumentTemplateVersion::query()->findOrFail($validated['version_id']);
        $template = $this->service->publish($documentTemplate, $version, $request->user());

        return response()->json($template);
    }

    public function generate(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorize('view', $documentTemplate);

        $validated = $request->validate([
            'variables' => 'required|array',
            'correspondence_id' => 'nullable|exists:correspondences,id',
            'transmission_slip_id' => 'nullable|exists:transmission_slips,id',
        ]);

        $document = $this->service->generateDocument(
            $documentTemplate,
            $request->user(),
            $validated['variables'],
            $validated['correspondence_id'] ?? null,
            $validated['transmission_slip_id'] ?? null,
        );

        return response()->json($document, 201);
    }
}
