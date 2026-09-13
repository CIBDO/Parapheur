<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFolder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkspaceFolderService
{
    public function create(Workspace $workspace, User $actor, string $name, ?int $parentId = null, ?string $description = null): WorkspaceFolder
    {
        if ($parentId) {
            $parent = WorkspaceFolder::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($parentId)
                ->firstOrFail();
        }

        $position = (int) WorkspaceFolder::query()
            ->where('workspace_id', $workspace->id)
            ->where('parent_id', $parentId)
            ->max('position') + 1;

        $folder = WorkspaceFolder::query()->create([
            'workspace_id' => $workspace->id,
            'parent_id' => $parentId,
            'name' => $name,
            'description' => $description,
            'owner_id' => $actor->id,
            'position' => $position,
            'path' => '/tmp/',
            'depth' => 0,
        ]);

        $folder->rebuildPath();

        app(WorkspaceActivityService::class)->record(
            $workspace,
            $actor,
            'folder_created',
            $actor->name.' a créé le dossier « '.$folder->name.' »',
            $folder,
        );

        return $folder->fresh(['parent', 'children']);
    }

    public function rename(WorkspaceFolder $folder, string $name, ?string $description = null): WorkspaceFolder
    {
        $folder->name = $name;
        if ($description !== null) {
            $folder->description = $description;
        }
        $folder->save();

        return $folder->fresh();
    }

    public function move(WorkspaceFolder $folder, ?int $newParentId): WorkspaceFolder
    {
        if ($newParentId !== null && (int) $newParentId === (int) $folder->id) {
            throw new InvalidArgumentException('Un dossier ne peut pas être son propre parent.');
        }

        if ($newParentId !== null) {
            $parent = WorkspaceFolder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->whereKey($newParentId)
                ->firstOrFail();

            if (str_starts_with($parent->path, $folder->path)) {
                throw new InvalidArgumentException('Impossible de déplacer un dossier dans l’un de ses descendants.');
            }
        }

        return DB::transaction(function () use ($folder, $newParentId) {
            $oldPath = $folder->path;
            $folder->parent_id = $newParentId;
            $folder->rebuildPath();

            $descendants = WorkspaceFolder::query()
                ->where('workspace_id', $folder->workspace_id)
                ->where('path', 'like', $oldPath.'%')
                ->where('id', '!=', $folder->id)
                ->orderBy('depth')
                ->get();

            foreach ($descendants as $child) {
                $child->rebuildPath();
            }

            return $folder->fresh(['parent', 'children']);
        });
    }

    public function softDelete(WorkspaceFolder $folder): void
    {
        $folder->delete();
    }

    public function restore(WorkspaceFolder $folder): WorkspaceFolder
    {
        $folder->restore();

        return $folder->fresh();
    }

    /**
     * @return Collection<int, WorkspaceFolder>
     */
    public function tree(Workspace $workspace): Collection
    {
        $folders = WorkspaceFolder::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return $this->buildTree($folders);
    }

    /**
     * @return list<array{id: int, name: string, path: string}>
     */
    public function breadcrumb(WorkspaceFolder $folder): array
    {
        $ids = array_values(array_filter(explode('/', trim($folder->path, '/'))));
        if ($ids === []) {
            return [['id' => $folder->id, 'name' => $folder->name, 'path' => $folder->path]];
        }

        $folders = WorkspaceFolder::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $crumbs = [];
        foreach ($ids as $id) {
            $f = $folders->get((int) $id);
            if ($f) {
                $crumbs[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'path' => $f->path,
                ];
            }
        }

        return $crumbs;
    }

    /**
     * @param  Collection<int, WorkspaceFolder>  $folders
     * @return Collection<int, WorkspaceFolder>
     */
    private function buildTree(Collection $folders, ?int $parentId = null): Collection
    {
        return $folders
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (WorkspaceFolder $folder) use ($folders) {
                $folder->setRelation('children', $this->buildTree($folders, $folder->id));

                return $folder;
            });
    }
}
