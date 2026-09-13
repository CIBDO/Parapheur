<?php

namespace App\Services;

use App\Enums\WorkspaceShareAbility;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFolder;
use App\Models\WorkspaceShare;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WorkspaceShareService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function share(
        Workspace $workspace,
        User $actor,
        User $grantee,
        WorkspaceShareAbility $ability,
        ?int $folderId = null,
        ?int $documentId = null,
        ?string $validFrom = null,
        ?string $validUntil = null,
    ): WorkspaceShare {
        if ((int) $grantee->id === (int) $actor->id) {
            throw new InvalidArgumentException('Impossible de se partager un élément à soi-même.');
        }

        if ($folderId) {
            WorkspaceFolder::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($folderId)
                ->firstOrFail();
        }

        if ($documentId) {
            Document::query()->whereKey($documentId)->firstOrFail();
        }

        $share = WorkspaceShare::query()->create([
            'workspace_id' => $workspace->id,
            'folder_id' => $folderId,
            'document_id' => $documentId,
            'grantee_user_id' => $grantee->id,
            'ability' => $ability->value,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'shared_by' => $actor->id,
        ]);

        $this->audit->log('workspace.shared', $workspace, [
            'share_id' => $share->id,
            'grantee_user_id' => $grantee->id,
            'ability' => $ability->value,
            'actor_id' => $actor->id,
        ]);

        return $share->fresh(['grantee', 'folder', 'document', 'sharedBy']);
    }

    /**
     * @return Collection<int, WorkspaceShare>
     */
    public function sharedWithMe(User $user): Collection
    {
        return WorkspaceShare::query()
            ->with(['workspace', 'folder', 'document', 'sharedBy:id,name,email'])
            ->where('grantee_user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function revoke(WorkspaceShare $share, User $actor): void
    {
        $workspace = $share->workspace;
        $shareId = $share->id;
        $share->delete();

        $this->audit->log('workspace.share_revoked', $workspace, [
            'share_id' => $shareId,
            'actor_id' => $actor->id,
        ]);
    }
}
