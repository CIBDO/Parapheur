<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Enums\WorkspaceShareAbility;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceShare;
use App\Services\WorkspaceShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class WorkspaceShareController extends Controller
{
    public function __construct(
        private readonly WorkspaceShareService $shares,
    ) {}

    public function sharedWithMe(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        return response()->json($this->shares->sharedWithMe($request->user()));
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('share', $workspace);

        $data = $request->validate([
            'grantee_user_id' => ['required', 'exists:users,id'],
            'ability' => ['required', Rule::in(array_column(WorkspaceShareAbility::cases(), 'value'))],
            'folder_id' => ['nullable', 'exists:workspace_folders,id'],
            'document_id' => ['nullable', 'exists:documents,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        try {
            $share = $this->shares->share(
                $workspace,
                $request->user(),
                User::query()->findOrFail($data['grantee_user_id']),
                WorkspaceShareAbility::from($data['ability']),
                $data['folder_id'] ?? null,
                $data['document_id'] ?? null,
                $data['valid_from'] ?? null,
                $data['valid_until'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($share, 201);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceShare $share): JsonResponse
    {
        $this->authorize('share', $workspace);
        abort_unless((int) $share->workspace_id === (int) $workspace->id, 404);

        $this->shares->revoke($share, $request->user());

        return response()->json(['message' => 'Partage révoqué.']);
    }
}
