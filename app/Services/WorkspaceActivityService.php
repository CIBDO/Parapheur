<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class WorkspaceActivityService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        Workspace $workspace,
        User $actor,
        string $action,
        string $summary,
        ?Model $subject = null,
        array $meta = [],
    ): WorkspaceActivity {
        return WorkspaceActivity::query()->create([
            'workspace_id' => $workspace->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'summary' => $summary,
            'meta' => $meta ?: null,
        ]);
    }

    public function feed(Workspace $workspace, int $perPage = 30): LengthAwarePaginator
    {
        return WorkspaceActivity::query()
            ->with(['actor:id,name,email'])
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
