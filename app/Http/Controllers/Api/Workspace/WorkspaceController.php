<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceType;
use App\Enums\WorkspaceVisibility;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocumentLink;
use App\Models\WorkspaceFolder;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceDocumentService;
use App\Services\WorkspaceFavoriteService;
use App\Services\WorkspaceFolderService;
use App\Services\WorkspaceQuotaService;
use App\Services\WorkspaceService;
use App\Services\WorkspaceShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly WorkspaceFolderService $folders,
        private readonly WorkspaceDocumentService $documents,
        private readonly WorkspaceQuotaService $quotas,
        private readonly WorkspaceShareService $shares,
        private readonly WorkspaceFavoriteService $favorites,
    ) {}

    public function home(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        return response()->json($this->workspaces->home($request->user()));
    }

    public function collaborative(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        return response()->json($this->workspaces->listCollaboratifs($request->user()));
    }

    public function sharedWithMe(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        $filter = $request->query('filter', 'all');
        $items = $this->shares->sharedWithMe($request->user())->map(function ($share) {
            if ($share->document_id) {
                return [
                    'id' => $share->id,
                    'kind' => 'document',
                    'title' => $share->document?->title ?? $share->document?->object,
                    'object' => $share->document?->object,
                    'created_at' => $share->created_at,
                    'shared_at' => $share->created_at,
                    'ability' => $share->ability,
                ];
            }
            if ($share->folder_id) {
                return [
                    'id' => $share->id,
                    'kind' => 'folder',
                    'name' => $share->folder?->name,
                    'created_at' => $share->created_at,
                    'shared_at' => $share->created_at,
                    'ability' => $share->ability,
                ];
            }

            return [
                'id' => $share->id,
                'kind' => 'workspace',
                'name' => $share->workspace?->name,
                'type' => $share->workspace?->type,
                'created_at' => $share->created_at,
                'ability' => $share->ability,
            ];
        });

        if ($filter === 'documents') {
            $items = $items->where('kind', 'document')->values();
        } elseif ($filter === 'folders') {
            $items = $items->where('kind', 'folder')->values();
        } elseif ($filter === 'workspaces') {
            $items = $items->where('kind', 'workspace')->values();
        }

        return response()->json(['data' => $items->values()]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        return response()->json([
            'data' => $this->favorites->listFor($request->user()),
        ]);
    }

    public function toggleFavorite(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->favorites->toggle($request->user(), $data['type'], (int) $data['id']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            ...$result,
            'message' => $result['favorited'] ? 'Ajouté aux favoris.' : 'Retiré des favoris.',
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        return response()->json([
            'viewed' => $this->favorites->viewed($request->user()),
            'modified' => $this->favorites->modified($request->user()),
            'data' => $this->favorites->modified($request->user()),
        ]);
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json($this->workspaces->show($workspace, $request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', Rule::in([
                WorkspaceType::Shared->value,
                WorkspaceType::Team->value,
                WorkspaceType::Project->value,
            ])],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'visibility' => ['nullable', Rule::in(array_column(WorkspaceVisibility::cases(), 'value'))],
            'quota_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->workspaces->createShared($request->user(), $data), 201);
    }

    public function browse(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $data = $request->validate([
            'folder_id' => ['nullable', 'integer'],
        ]);

        $folderId = isset($data['folder_id']) ? (int) $data['folder_id'] : null;

        $childFoldersQuery = WorkspaceFolder::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('position')
            ->orderBy('name');

        if ($folderId === null) {
            $childFoldersQuery->whereNull('parent_id');
        } else {
            $childFoldersQuery->where('parent_id', $folderId);
        }

        $childFolders = $childFoldersQuery->get();

        // À la racine : uniquement les documents sans dossier ; sinon ceux du dossier courant
        $documentLinks = $this->documents->list(
            $workspace,
            $folderId,
            $folderId === null ? ['folder_id' => null] : [],
            100
        );

        $currentFolder = $folderId
            ? WorkspaceFolder::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($folderId)
                ->firstOrFail()
            : null;

        return response()->json([
            'folders' => $childFolders,
            'documents' => collect($documentLinks->items())->map(fn ($link) => $link->document)->filter()->values(),
            'breadcrumb' => $currentFolder ? $this->folders->breadcrumb($currentFolder) : [],
            'current_folder' => $currentFolder,
            'storage' => $this->quotas->storagePayload($workspace),
        ]);
    }

    public function storage(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json($this->quotas->storagePayload($workspace));
    }

    public function trash(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $folders = WorkspaceFolder::onlyTrashed()
            ->where('workspace_id', $workspace->id)
            ->get();

        $docIds = WorkspaceDocumentLink::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('document_id');

        $documents = Document::onlyTrashed()
            ->whereIn('id', $docIds)
            ->get();

        return response()->json([
            'folders' => $folders,
            'documents' => $documents,
        ]);
    }

    public function restoreTrash(Request $request, Workspace $workspace, int $id): JsonResponse
    {
        $this->authorize('contribute', $workspace);

        $folder = WorkspaceFolder::onlyTrashed()
            ->where('workspace_id', $workspace->id)
            ->whereKey($id)
            ->first();

        if ($folder) {
            return response()->json($this->folders->restore($folder));
        }

        $document = Document::onlyTrashed()->whereKey($id)->firstOrFail();
        $link = $this->documents->restoreDocument($document, $workspace, $request->user());

        return response()->json($link);
    }

    public function addMember(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::in([
                WorkspaceMemberRole::Manager->value,
                WorkspaceMemberRole::Editor->value,
                WorkspaceMemberRole::Contributor->value,
                WorkspaceMemberRole::Viewer->value,
            ])],
        ]);

        try {
            $member = $this->workspaces->addMember(
                $workspace,
                $request->user(),
                User::query()->findOrFail($data['user_id']),
                WorkspaceMemberRole::from($data['role']),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($member, 201);
    }

    public function updateMember(Request $request, Workspace $workspace, WorkspaceMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);

        $data = $request->validate([
            'role' => ['required', Rule::in([
                WorkspaceMemberRole::Manager->value,
                WorkspaceMemberRole::Editor->value,
                WorkspaceMemberRole::Contributor->value,
                WorkspaceMemberRole::Viewer->value,
            ])],
        ]);

        try {
            $updated = $this->workspaces->updateMemberRole(
                $member,
                WorkspaceMemberRole::from($data['role']),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($updated);
    }

    public function removeMember(Request $request, Workspace $workspace, WorkspaceMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);

        try {
            $this->workspaces->removeMember($member, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Membre retiré.']);
    }
}
