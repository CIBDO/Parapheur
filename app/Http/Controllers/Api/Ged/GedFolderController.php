<?php

namespace App\Http\Controllers\Api\Ged;

use App\Enums\DocumentAccessAbility;
use App\Enums\DocumentFolderKind;
use App\Enums\PublicationWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentFolderAccessRule;
use App\Policies\DocumentPolicy;
use App\Services\DocumentFolderService;
use App\Services\DocumentSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GedFolderController extends Controller
{
    public function __construct(
        private readonly DocumentFolderService $folders,
        private readonly DocumentSearchService $search,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $includeArchived = filter_var($request->query('include_archived'), FILTER_VALIDATE_BOOLEAN);

        return response()->json([
            'data' => $this->folders->tree($includeArchived),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageFolders($request->user()), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:document_folders,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'kind' => ['nullable', Rule::in(array_column(DocumentFolderKind::cases(), 'value'))],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'inherit_permissions' => ['nullable', 'boolean'],
            'default_publication_workflow' => ['nullable', Rule::in(array_column(PublicationWorkflow::cases(), 'value'))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $folder = $this->folders->create($request->user(), $data);

        return response()->json($folder, 201);
    }

    public function show(Request $request, DocumentFolder $folder): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $folder->load(['parent', 'children', 'structure', 'responsible', 'accessRules.user', 'accessRules.structure']);

        return response()->json([
            'folder' => $folder,
            'breadcrumb' => $this->folders->breadcrumb($folder),
            'documents' => $this->search->search($request->user(), [
                'folder_id' => $folder->id,
                'sort' => $request->query('sort', 'updated_at'),
                'dir' => $request->query('dir', 'desc'),
            ], (int) $request->query('per_page', 25)),
        ]);
    }

    public function update(Request $request, DocumentFolder $folder): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageFolders($request->user()), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:50'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'kind' => ['nullable', Rule::in(array_column(DocumentFolderKind::cases(), 'value'))],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'inherit_permissions' => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
            'default_publication_workflow' => ['nullable', Rule::in(array_column(PublicationWorkflow::cases(), 'value'))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->folders->update($folder, $request->user(), $data));
    }

    public function move(Request $request, DocumentFolder $folder): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageFolders($request->user()), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:document_folders,id'],
        ]);

        return response()->json(
            $this->folders->move($folder, $data['parent_id'] ?? null, $request->user())
        );
    }

    public function destroy(Request $request, DocumentFolder $folder): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageFolders($request->user()), 403);

        $this->folders->softDelete($folder, $request->user());

        return response()->json(['message' => 'Dossier supprimé.']);
    }

    public function share(Request $request, DocumentFolder $folder): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageFolders($request->user()), 403);

        $data = $request->validate([
            'ability' => ['required', Rule::in(array_column(DocumentAccessAbility::cases(), 'value'))],
            'user_id' => ['nullable', 'exists:users,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'role_name' => ['nullable', 'string', 'max:100'],
            'is_exception' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $rule = $this->folders->grantAccess(
            $folder,
            $request->user(),
            DocumentAccessAbility::from($data['ability']),
            $data['user_id'] ?? null,
            $data['structure_id'] ?? null,
            $data['role_name'] ?? null,
            (bool) ($data['is_exception'] ?? false),
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        return response()->json($rule, 201);
    }

    public function revokeShare(Request $request, DocumentFolder $folder, DocumentFolderAccessRule $rule): JsonResponse
    {
        abort_unless(app(DocumentPolicy::class)->manageFolders($request->user()), 403);
        abort_unless((int) $rule->document_folder_id === (int) $folder->id, 404);

        $this->folders->revokeAccess($rule, $request->user());

        return response()->json(['message' => 'Accès révoqué.']);
    }
}
