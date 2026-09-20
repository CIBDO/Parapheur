<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Enums\WorkspaceMemberRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceActivityService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly WorkspaceActivityService $activities,
    ) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json([
            'data' => $workspace->members()->with('user:id,name,email,structure_id')->orderBy('role')->get(),
        ]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
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

    public function update(Request $request, Workspace $workspace, WorkspaceMember $member): JsonResponse
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
            $member = $this->workspaces->updateMemberRole(
                $member,
                WorkspaceMemberRole::from($data['role']),
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($member);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceMember $member): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);

        try {
            $this->workspaces->removeMember($member, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function activity(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $perPage = (int) $request->query('per_page', 30);

        return response()->json($this->activities->feed($workspace, min(100, max(1, $perPage))));
    }
}
