<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceFolder;
use App\Services\WorkspaceAccessService;
use App\Services\WorkspaceFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WorkspaceFolderController extends Controller
{
    public function __construct(
        private readonly WorkspaceFolderService $folders,
        private readonly WorkspaceAccessService $access,
    ) {}

    public function tree(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json($this->folders->tree($workspace));
    }

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json($this->folders->tree($workspace));
    }

    public function move(Request $request, Workspace $workspace, WorkspaceFolder $folder): JsonResponse
    {
        $this->authorize('view', $workspace);
        abort_unless((int) $folder->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->access->canManageFolders($request->user(), $workspace), 403);

        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:workspace_folders,id'],
        ]);

        try {
            $folder = $this->folders->move($folder, $data['parent_id'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($folder);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);
        abort_unless($this->access->canManageFolders($request->user(), $workspace), 403, 'Gestion des dossiers refusée.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:workspace_folders,id'],
        ]);

        $folder = $this->folders->create(
            $workspace,
            $request->user(),
            $data['name'],
            $data['parent_id'] ?? null,
            $data['description'] ?? null,
        );

        return response()->json($folder, 201);
    }

    public function update(Request $request, Workspace $workspace, WorkspaceFolder $folder): JsonResponse
    {
        $this->authorize('view', $workspace);
        abort_unless((int) $folder->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->access->canManageFolders($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:workspace_folders,id'],
        ]);

        try {
            if (array_key_exists('parent_id', $data)) {
                $folder = $this->folders->move($folder, $data['parent_id']);
            }
            if (isset($data['name'])) {
                $folder = $this->folders->rename($folder, $data['name'], $data['description'] ?? null);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($folder);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceFolder $folder): JsonResponse
    {
        $this->authorize('view', $workspace);
        abort_unless((int) $folder->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->access->canManageFolders($request->user(), $workspace), 403);

        try {
            $this->folders->softDelete($folder);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Dossier placé en corbeille.']);
    }

    public function restore(Request $request, Workspace $workspace, int $folder): JsonResponse
    {
        $this->authorize('view', $workspace);
        abort_unless($this->access->canManageFolders($request->user(), $workspace), 403);

        $model = WorkspaceFolder::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->whereKey($folder)
            ->firstOrFail();

        return response()->json($this->folders->restore($model));
    }

    public function breadcrumb(Request $request, Workspace $workspace, WorkspaceFolder $folder): JsonResponse
    {
        $this->authorize('view', $workspace);
        abort_unless((int) $folder->workspace_id === (int) $workspace->id, 404);

        return response()->json($this->folders->breadcrumb($folder));
    }
}
