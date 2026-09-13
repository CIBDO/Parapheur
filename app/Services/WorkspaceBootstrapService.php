<?php

namespace App\Services;

use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceType;
use App\Enums\WorkspaceVisibility;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFolder;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceStoragePolicy;
use Illuminate\Support\Facades\DB;

class WorkspaceBootstrapService
{
    /** @var list<string> */
    public const DEFAULT_PERSONAL_FOLDERS = [
        'Mes projets',
        'Modèles',
        'Références',
        'Archives personnelles',
    ];

    public function ensureStoragePolicy(): WorkspaceStoragePolicy
    {
        $policy = WorkspaceStoragePolicy::query()->first();

        if ($policy) {
            return $policy;
        }

        return WorkspaceStoragePolicy::query()->create([]);
    }

    public function ensurePersonalWorkspace(User $user): Workspace
    {
        $existing = Workspace::query()
            ->where('type', WorkspaceType::Personal->value)
            ->where('owner_id', $user->id)
            ->first();

        if ($existing) {
            $this->ensureOwnerMembership($existing, $user);
            $this->ensureDefaultFolders($existing, $user);
            $this->ensureStoragePolicy();

            return $existing->fresh(['members', 'folders']);
        }

        return DB::transaction(function () use ($user) {
            $this->ensureStoragePolicy();

            $workspace = Workspace::query()->create([
                'name' => 'Espace personnel — '.$user->name,
                'description' => 'Espace documentaire personnel',
                'type' => WorkspaceType::Personal->value,
                'owner_id' => $user->id,
                'structure_id' => $user->structure_id,
                'visibility' => WorkspaceVisibility::Private->value,
            ]);

            $this->ensureOwnerMembership($workspace, $user);
            $this->ensureDefaultFolders($workspace, $user);

            return $workspace->fresh(['members', 'folders']);
        });
    }

    private function ensureOwnerMembership(Workspace $workspace, User $user): void
    {
        WorkspaceMember::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ],
            [
                'role' => WorkspaceMemberRole::Owner->value,
                'joined_at' => now(),
            ]
        );
    }

    private function ensureDefaultFolders(Workspace $workspace, User $user): void
    {
        foreach (self::DEFAULT_PERSONAL_FOLDERS as $index => $name) {
            $folder = WorkspaceFolder::query()
                ->where('workspace_id', $workspace->id)
                ->whereNull('parent_id')
                ->where('name', $name)
                ->first();

            if ($folder) {
                continue;
            }

            $folder = WorkspaceFolder::query()->create([
                'workspace_id' => $workspace->id,
                'parent_id' => null,
                'name' => $name,
                'owner_id' => $user->id,
                'position' => $index,
                'path' => '/tmp/',
                'depth' => 0,
            ]);

            $folder->rebuildPath();
        }
    }
}
