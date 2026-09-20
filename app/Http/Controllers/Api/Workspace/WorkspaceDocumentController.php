<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Workspace;
use App\Models\WorkspaceDocumentLink;
use App\Services\DocumentBridgeService;
use App\Services\WorkspaceAccessService;
use App\Services\WorkspaceDocumentService;
use App\Support\AllowedDocumentUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WorkspaceDocumentController extends Controller
{
    public function __construct(
        private readonly WorkspaceDocumentService $documents,
        private readonly WorkspaceAccessService $access,
        private readonly DocumentBridgeService $bridge,
    ) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $data = $request->validate([
            'folder_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // Sans folder_id : tous les documents de l’espace (pas seulement la racine)
        $folderId = array_key_exists('folder_id', $data) ? $data['folder_id'] : false;
        $filters = $data;
        if ($folderId === false) {
            $folderId = null;
            unset($filters['folder_id']);
        }

        $paginator = $this->documents->list(
            $workspace,
            is_int($folderId) ? $folderId : null,
            $filters,
            $data['per_page'] ?? 25
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(function (WorkspaceDocumentLink $link) {
                $document = $link->document;
                if (! $document) {
                    return null;
                }

                return array_merge($document->toArray(), [
                    'link_id' => $link->id,
                    'folder_id' => $link->folder_id,
                    'folder' => $link->folder ? [
                        'id' => $link->folder->id,
                        'name' => $link->folder->name,
                        'path' => $link->folder->path,
                    ] : null,
                    'added_at' => $link->created_at,
                    'size' => $document->latestVersion?->size,
                    'latest_version' => $document->latestVersion,
                ]);
            })->filter()->values()
        );

        return response()->json($paginator);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        return $this->upload($request, $workspace);
    }

    public function upload(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('contribute', $workspace);

        $data = $request->validate([
            'object' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'folder_id' => ['nullable', 'exists:workspace_folders,id'],
            'confidentiality' => ['nullable', 'string'],
            'main_file' => AllowedDocumentUploads::fileRules(true),
            'tags' => ['nullable'],
        ]);

        try {
            $link = $this->documents->upload(
                $workspace,
                $request->user(),
                $data,
                $request->file('main_file'),
                $data['folder_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $document = $link->document->fresh(['type', 'latestVersion', 'author']);

        return response()->json(array_merge($document->toArray(), [
            'link_id' => $link->id,
            'folder_id' => $link->folder_id,
            'document_id' => $document->id,
            'origin' => $document->origin?->value ?? (string) $document->origin,
        ]), 201);
    }

    public function move(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('contribute', $workspace);

        $link = WorkspaceDocumentLink::query()
            ->where('workspace_id', $workspace->id)
            ->where('document_id', $document->id)
            ->firstOrFail();

        $data = $request->validate([
            'folder_id' => ['nullable', 'exists:workspace_folders,id'],
        ]);

        return response()->json($this->documents->move($link, $data['folder_id'] ?? null));
    }

    public function destroy(Request $request, Workspace $workspace, Document $document): JsonResponse
    {
        $this->authorize('contribute', $workspace);

        $link = WorkspaceDocumentLink::query()
            ->where('workspace_id', $workspace->id)
            ->where('document_id', $document->id)
            ->firstOrFail();

        try {
            $this->documents->trash($link, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Document placé en corbeille.']);
    }

    public function restore(Request $request, Workspace $workspace, int $document): JsonResponse
    {
        $this->authorize('contribute', $workspace);

        $doc = Document::withTrashed()->whereKey($document)->firstOrFail();

        $data = $request->validate([
            'folder_id' => ['nullable', 'exists:workspace_folders,id'],
        ]);

        $link = $this->documents->restoreDocument($doc, $workspace, $request->user(), $data['folder_id'] ?? null);

        return response()->json($link);
    }

    public function submitToGed(Request $request, Workspace $workspace, WorkspaceDocumentLink $link): JsonResponse
    {
        $this->authorize('view', $workspace);
        abort_unless((int) $link->workspace_id === (int) $workspace->id, 404);

        $data = $request->validate([
            'classification_node_id' => ['nullable', 'exists:classification_nodes,id'],
        ]);

        try {
            $document = $this->bridge->submitToGed(
                $link->document,
                $request->user(),
                $data['classification_node_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($document);
    }
}
