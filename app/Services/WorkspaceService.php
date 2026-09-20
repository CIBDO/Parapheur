<?php

namespace App\Services;

use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceType;
use App\Enums\WorkspaceVisibility;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceMemberNotification;
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

        $shared = $this->listCollaboratifs($user);

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
     * @return Collection<int, array<string, mixed>>
     */
    public function listCollaboratifs(User $user): Collection
    {
        $workspaces = Workspace::query()
            ->where('type', '!=', WorkspaceType::Personal->value)
            ->where(function ($q) use ($user) {
                $q->whereHas('members', fn ($m) => $m->where('user_id', $user->id));
                if ($user->can('admin.access')) {
                    $q->orWhereRaw('1=1');
                }
            })
            ->withCount('members')
            ->with(['owner:id,name,email'])
            ->orderBy('name')
            ->get();

        return $workspaces->map(fn (Workspace $ws) => $this->serializeWorkspace($ws, $user));
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, User $actor, array $data): Workspace
    {
        abort_unless($this->access->canManage($actor, $workspace), 403, 'Modification de l’espace refusée.');

        if (isset($data['name'])) {
            $workspace->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $workspace->description = $data['description'];
        }
        if (isset($data['type'])) {
            $workspace->type = $data['type'];
        }
        if (isset($data['visibility'])) {
            $workspace->visibility = $data['visibility'];
        }

        $workspace->save();

        $this->audit->log('workspace.updated', $workspace, [
            'actor_id' => $actor->id,
        ]);

        app(WorkspaceActivityService::class)->record(
            $workspace,
            $actor,
            'workspace_updated',
            $actor->name.' a mis à jour les informations de l’espace',
            $workspace,
        );

        return $workspace->fresh(['owner:id,name,email', 'members.user:id,name,email']);
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Workspace $workspace, User $user): array
    {
        abort_unless($this->access->canView($user, $workspace), 403, 'Accès à l’espace refusé.');

        $workspace->loadCount('members');
        $workspace->load([
            'owner:id,name,email',
            'members.user:id,name,email,structure_id',
            'folders' => fn ($q) => $q->whereNull('parent_id')->orderBy('position'),
        ]);

        $serialized = $this->serializeWorkspace($workspace, $user);

        return [
            'workspace' => array_merge($serialized, [
                'folders' => $workspace->folders,
                'members' => $workspace->members,
            ]),
            'can_manage_members' => $this->access->canManage($user, $workspace),
            'can_edit' => $this->access->canEdit($user, $workspace),
            'can_contribute' => $this->access->canContribute($user, $workspace),
            'storage' => $this->quotas->storagePayload($workspace),
        ];
    }

    /** @deprecated use showPayload */
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

        try {
            $member->notify(new WorkspaceMemberNotification(
                workspace: $workspace,
                event: 'member_added',
                roleLabel: $role->label(),
                actorName: $actor->name,
            ));
        } catch (\Throwable $e) {
            report($e);
        }

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

        $workspace = $member->workspace;
        $member->role = $role;
        $member->save();

        $this->audit->log('workspace.member_role_updated', $workspace, [
            'actor_id' => $actor->id,
            'user_id' => $member->user_id,
            'role' => $role->value,
        ]);

        app(WorkspaceActivityService::class)->record(
            $workspace,
            $actor,
            'member_role_changed',
            $actor->name.' a modifié le rôle de '.($member->user?->name ?? 'un membre').' ('.$role->label().')',
            $member->user,
        );

        if ($member->user) {
            try {
                $member->user->notify(new WorkspaceMemberNotification(
                    workspace: $workspace,
                    event: 'member_role_changed',
                    roleLabel: $role->label(),
                    actorName: $actor->name,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $member->fresh(['user']);
    }

    public function removeMember(WorkspaceMember $member, User $actor): void
    {
        if ($member->role === WorkspaceMemberRole::Owner) {
            throw new InvalidArgumentException('Le propriétaire ne peut pas être retiré.');
        }

        $workspace = $member->workspace;
        $userName = $member->user?->name ?? 'un membre';
        $userId = $member->user_id;
        $member->delete();

        $this->audit->log('workspace.member_removed', $workspace, [
            'actor_id' => $actor->id,
            'user_id' => $userId,
        ]);

        app(WorkspaceActivityService::class)->record(
            $workspace,
            $actor,
            'member_removed',
            $actor->name.' a retiré '.$userName,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorkspace(Workspace $ws, User $user): array
    {
        $role = $this->access->roleFor($user, $ws);

        return [
            'id' => $ws->id,
            'name' => $ws->name,
            'description' => $ws->description,
            'type' => $ws->type?->value ?? (string) $ws->type,
            'visibility' => $ws->visibility?->value ?? (string) $ws->visibility,
            'owner_id' => $ws->owner_id,
            'owner' => $ws->owner,
            'structure_id' => $ws->structure_id,
            'members_count' => (int) ($ws->members_count ?? $ws->members()->count()),
            'my_role' => $role?->value,
            'can_manage_members' => $this->access->canManage($user, $ws),
            'can_edit' => $this->access->canEdit($user, $ws),
            'updated_at' => $ws->updated_at,
            'created_at' => $ws->created_at,
        ];
    }
}
