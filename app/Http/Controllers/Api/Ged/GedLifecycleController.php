<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Jobs\IndexDocumentContentJob;
use App\Models\Document;
use App\Models\DocumentClassificationRule;
use App\Models\DocumentRetentionRule;
use App\Models\DocumentType;
use App\Policies\DocumentPolicy;
use App\Services\ArchivePackService;
use App\Services\DocumentAccessService;
use App\Services\DocumentRetentionService;
use App\Services\DocumentSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GedLifecycleController extends Controller
{
    public function __construct(
        private readonly DocumentRetentionService $retention,
        private readonly DocumentAccessService $access,
        private readonly ArchivePackService $packs,
        private readonly DocumentSearchService $search,
    ) {}

    public function legalHold(Request $request, Document $document): JsonResponse
    {
        $this->authorize('archive', $document);
        abort_unless($request->user()->can('ged.manage_retention') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $document = $this->retention->placeLegalHold($document, $request->user(), $data['reason']);

        return response()->json($document);
    }

    public function releaseLegalHold(Request $request, Document $document): JsonResponse
    {
        $this->authorize('archive', $document);
        abort_unless($request->user()->can('ged.manage_retention') || $request->user()->can('admin.access'), 403);

        $document = $this->retention->releaseLegalHold($document, $request->user());

        return response()->json($document);
    }

    public function reindex(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        IndexDocumentContentJob::dispatch($document->id);

        return response()->json(['message' => 'Indexation planifiée.']);
    }

    public function export(Request $request, Document $document): BinaryFileResponse|JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        $this->access->authorizeDownload($request->user(), $document);

        try {
            $this->retention->assertNotInfected($document);
            $pack = $this->packs->build($document, $request->user(), allowActive: true);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->download($pack['path'], $pack['filename'], [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function indicators(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        return response()->json($this->search->indicators($request->user()));
    }

    public function retentionRules(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('ged.manage_retention')
            || $request->user()->can('admin.access')
            || $request->user()->can('ged.view'),
            403
        );

        return response()->json([
            'data' => DocumentRetentionRule::query()->with(['documentType', 'category'])->orderBy('name')->get(),
        ]);
    }

    public function storeRetentionRule(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('ged.manage_retention') || $request->user()->can('admin.access'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:document_retention_rules,code'],
            'name' => ['required', 'string', 'max:255'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'category_id' => ['nullable', 'exists:document_categories,id'],
            'retention_years' => ['required', 'integer', 'min:1', 'max:100'],
            'final_disposition' => ['nullable', Rule::in(['archiver', 'conserver', 'detruire_apres_validation'])],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = DocumentRetentionRule::query()->create($data + [
            'final_disposition' => $data['final_disposition'] ?? 'archiver',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($rule, 201);
    }

    public function classificationRules(Request $request): JsonResponse
    {
        abort_unless(
            app(DocumentPolicy::class)->manageClassification($request->user())
            || $request->user()->can('ged.view'),
            403
        );

        return response()->json([
            'data' => DocumentClassificationRule::query()
                ->with(['documentType', 'structure', 'targetNode'])
                ->orderBy('priority')
                ->get(),
        ]);
    }

    public function storeClassificationRule(Request $request): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageClassification($request->user()), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:document_classification_rules,code'],
            'name' => ['required', 'string', 'max:255'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'target_classification_node_id' => ['required', 'exists:classification_nodes,id'],
            'trigger_status' => ['nullable', Rule::in(['valide', 'traite', 'archive'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = DocumentClassificationRule::query()->create($data + [
            'trigger_status' => $data['trigger_status'] ?? 'valide',
            'priority' => $data['priority'] ?? 100,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($rule->load(['targetNode', 'documentType']), 201);
    }
}
