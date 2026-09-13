<?php

namespace App\Services;

use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceType;
use App\Enums\WorkspaceVisibility;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkspaceService
{
    public function __construct(
        private readonly WorkspaceBootstrapService $bootstrap,
        private readonly WorkspaceAccessService $access,
        private readonly WorkspaceQuotaService $quotas,
        private readonly WorkspaceFolderService $folders,
        private readonly WorkspaceFavoriteService $favorites,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function home(User $user): array
    {
        $personal = $this->bootstrap->ensurePersonalWorkspace($user);

        $shared = Workspace::query()
            ->where('type', '!=', WorkspaceType::Personal->value)
            ->whereHas('members', fn ($m) => $m->where('user_id', $user->id))
            ->with(['owner:id,name,email', 'members'])
            ->orderBy('name')
            ->get();

        $extras = $this->favorites->homeExtras($user);

        return [
            'workspace' => $personal->load(['folders' => fn ($q) => $q->whereNull('parent_id')->orderBy('position')]),
            'personal' => $personal,
            'folders' => $personal->folders()->whereNull('parent_id')->orderBy('position')->get(),
            'root_folders' => $personal->folders()->whereNull('parent_id')->orderBy('position')->get(),
            'collaboratifs' => $shared,
            'storage' => $this->quotas->storagePayload($personal),
            'tree' => $this->folders->tree($personal),
            'recent' => $extras['recent'],
            'viewed' => $extras['viewed'],
            'favorites' => $extras['favorites'],
        ];
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function listCollaboratifs(User $user): Collection
    {
        return Workspace::query()
            ->where('type', '!=', WorkspaceType::Personal->value)
            ->where(function ($q) use ($user) {
                $q->whereHas('members', fn ($m) => $m->where('user_id', $user->id));
                if ($user->can('admin.access')) {
                    $q->orWhereRaw('1=1');
                }
            })
            ->with(['owner:id,name,email', 'members.user:id,name,email'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createShared(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? WorkspaceType::Shared->value,
                'owner_id' => $owner->id,
                'structure_id' => $data['structure_id'] ?? $owner->structure_id,
                'visibility' => $data['visibility'] ?? WorkspaceVisibility::Restricted->value,
                'quota_bytes' => $data['quota_bytes'] ?? null,
            ]);

            WorkspaceMember::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => WorkspaceMemberRole::Owner->value,
                'joined_at' => now(),
            ]);

            $this->audit->log('workspace.created', $workspace, [
                'actor_id' => $owner->id,
                'type' => $workspace->type?->value ?? (string) $workspace->type,
            ]);

            return $workspace->fresh(['members.user', 'owner']);
        });
    }

    public function show(Workspace $workspace, User $user): Workspace
    {
        abort_unless($this->access->canView($user, $workspace), 403, 'Accès à l’espace refusé.');

        return $workspace->load([
            'owner:id,name,email',
            'members.user:id,name,email',
            'folders' => fn ($q) => $q->whereNull('parent_id')->orderBy('position'),
        ]);
    }

    public function addMember(Workspace $workspace, User $actor, User $member, WorkspaceMemberRole $role): WorkspaceMember
    {
        if ($role === WorkspaceMemberRole::Owner) {
            throw new InvalidArgumentException('Le rôle propriétaire ne peut pas être attribué ainsi.');
        }

        $record = WorkspaceMember::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $member->id,
            ],
            [
                'role' => $role->value,
                'joined_at' => now(),
            ]
        );

        $this->audit->log('workspace.member_added', $workspace, [
            'actor_id' => $actor->id,
            'user_id' => $member->id,
            'role' => $role->value,
        ]);

        app(WorkspaceActivityService::class)->record(
            $workspace,
            $actor,
            'member_added',
            $actor->name.' a ajouté '.$member->name.' ('.$role->label().')',
            $member,
        );

        return $record->fresh(['user']);
    }

    public function updateMemberRole(WorkspaceMember $member, WorkspaceMemberRole $role, User $actor): WorkspaceMember
    {
        if ($member->role === WorkspaceMemberRole::Owner) {
            throw new InvalidArgumentException('Le propriétaire ne peut pas changer de rôle.');
        }

        if ($role === WorkspaceMemberRole::Owner) {
            throw new InvalidArgumentException('Transfert de propriété non pris en charge ici.');
        }

        $member->role = $role;
        $member->save();

        $this->audit->log('workspace.member_role_updated', $member->workspace, [
            'actor_id' => $actor->id,
            'user_id' => $member->user_id,
            'role' => $role->value,
        ]);

        return $member->fresh(['user']);
    }

    public function removeMember(WorkspaceMember $member, User $actor): void
    {
        if ($member->role === WorkspaceMemberRole::Owner) {
            throw new InvalidArgumentException('Le propriétaire ne peut pas être retiré.');
        }

        $workspace = $member->workspace;
        $userId = $member->user_id;
        $member->delete();

        $this->audit->log('workspace.member_removed', $workspace, [
            'actor_id' => $actor->id,
            'user_id' => $userId,
        ]);
    }
}
