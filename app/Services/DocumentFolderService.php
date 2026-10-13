<?php

namespace App\Services;

use App\Enums\DocumentAccessAbility;
use App\Enums\DocumentFolderKind;
use App\Enums\PublicationWorkflow;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentFolderAccessRule;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentFolderService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): DocumentFolder
    {
        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            DocumentFolder::query()->whereKey($parentId)->firstOrFail();
        }

        $siblings = DocumentFolder::query();
        if ($parentId === null) {
            $siblings->whereNull('parent_id');
        } else {
            $siblings->where('parent_id', $parentId);
        }
        $sortOrder = (int) ($data['sort_order'] ?? ((int) $siblings->max('sort_order') + 1));

        $folder = DocumentFolder::query()->create([
            'parent_id' => $parentId,
            'structure_id' => $data['structure_id'] ?? null,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'kind' => $data['kind'] ?? DocumentFolderKind::Institutional->value,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'path' => '/tmp/',
            'depth' => 0,
            'inherit_permissions' => $data['inherit_permissions'] ?? true,
            'is_archived' => false,
            'default_publication_workflow' => $data['default_publication_workflow']
                ?? PublicationWorkflow::Controlled->value,
            'sort_order' => $sortOrder,
        ]);

        $folder->rebuildPath();
        $this->audit->log('document_folder.created', $folder, ['actor_id' => $actor->id]);

        return $folder->fresh(['parent', 'children', 'structure', 'responsible']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DocumentFolder $folder, User $actor, array $data): DocumentFolder
    {
        foreach ([
            'name', 'description', 'code', 'structure_id', 'kind', 'responsible_user_id',
            'inherit_permissions', 'is_archived', 'default_publication_workflow', 'sort_order',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $folder->{$field} = $data[$field];
            }
        }
        $folder->save();
        $this->audit->log('document_folder.updated', $folder, ['actor_id' => $actor->id]);

        return $folder->fresh(['parent', 'children', 'structure', 'responsible', 'accessRules']);
    }

    public function move(DocumentFolder $folder, ?int $newParentId, User $actor): DocumentFolder
    {
        if ($newParentId !== null && (int) $newParentId === (int) $folder->id) {
            throw new InvalidArgumentException('Un dossier ne peut pas être son propre parent.');
        }

        if ($newParentId !== null) {
            $parent = DocumentFolder::query()->whereKey($newParentId)->firstOrFail();
            if (str_starts_with((string) $parent->path, (string) $folder->path)) {
                throw new InvalidArgumentException('Impossible de déplacer un dossier dans l’un de ses descendants.');
            }
            $this->assertMoveDoesNotWidenAccess($folder, $parent);
        }

        return DB::transaction(function () use ($folder, $newParentId, $actor) {
            $oldPath = $folder->path;
            $folder->parent_id = $newParentId;
            $folder->rebuildPath();

            $descendants = DocumentFolder::query()
                ->where('path', 'like', $oldPath.'%')
                ->where('id', '!=', $folder->id)
                ->orderBy('depth')
                ->get();

            foreach ($descendants as $child) {
                $child->rebuildPath();
            }

            $this->audit->log('document_folder.moved', $folder, [
                'actor_id' => $actor->id,
                'new_parent_id' => $newParentId,
            ]);

            return $folder->fresh(['parent', 'children']);
        });
    }

    public function softDelete(DocumentFolder $folder, User $actor): void
    {
        if ($folder->documents()->exists() || $folder->children()->exists()) {
            throw new InvalidArgumentException('Le dossier contient encore des documents ou sous-dossiers.');
        }

        $folder->delete();
        $this->audit->log('document_folder.deleted', $folder, ['actor_id' => $actor->id]);
    }

    /**
     * @return Collection<int, DocumentFolder>
     */
    public function tree(?bool $includeArchived = false): Collection
    {
        $query = DocumentFolder::query()
            ->with(['structure:id,code,name', 'responsible:id,name'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if (! $includeArchived) {
            $query->where('is_archived', false);
        }

        return $this->buildTree($query->get());
    }

    /**
     * @return list<array{id: int, name: string, path: string}>
     */
    public function breadcrumb(DocumentFolder $folder): array
    {
        $ids = $folder->ancestorIdsIncludingSelf();
        $folders = DocumentFolder::query()->whereIn('id', $ids)->get()->keyBy('id');
        $crumbs = [];
        foreach ($ids as $id) {
            $f = $folders->get($id);
            if ($f) {
                $crumbs[] = ['id' => $f->id, 'name' => $f->name, 'path' => (string) $f->path];
            }
        }

        return $crumbs;
    }

    public function grantAccess(
        DocumentFolder $folder,
        User $actor,
        DocumentAccessAbility $ability,
        ?int $userId = null,
        ?int $structureId = null,
        ?string $roleName = null,
        bool $isException = false,
        ?\DateTimeInterface $expiresAt = null,
    ): DocumentFolderAccessRule {
        if (! $userId && ! $structureId && ! $roleName) {
            throw new InvalidArgumentException('Indiquer un utilisateur, une structure ou un rôle.');
        }

        $rule = DocumentFolderAccessRule::query()->create([
            'document_folder_id' => $folder->id,
            'user_id' => $userId,
            'structure_id' => $structureId,
            'role_name' => $roleName,
            'ability' => $ability->value,
            'is_exception' => $isException,
            'granted_by' => $actor->id,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->log('document_folder.access_granted', $folder, [
            'actor_id' => $actor->id,
            'ability' => $ability->value,
        ]);

        return $rule->load(['user', 'structure']);
    }

    public function revokeAccess(DocumentFolderAccessRule $rule, User $actor): void
    {
        $folder = $rule->folder;
        $rule->delete();
        $this->audit->log('document_folder.access_revoked', $folder, [
            'actor_id' => $actor->id,
            'rule_id' => $rule->id,
        ]);
    }

    public function userHasFolderAbility(User $user, DocumentFolder $folder, DocumentAccessAbility $ability): bool
    {
        if ($user->can('admin.access') || $user->can('ged.manage_folders')) {
            return true;
        }

        $folderIds = $folder->inherit_permissions
            ? $folder->ancestorIdsIncludingSelf()
            : [(int) $folder->id];

        $roleNames = $user->getRoleNames()->all();

        $exception = DocumentFolderAccessRule::query()
            ->whereIn('document_folder_id', $folderIds)
            ->where('ability', $ability->value)
            ->where('is_exception', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($user, $roleNames) {
                $q->where('user_id', $user->id);
                if ($user->structure_id) {
                    $q->orWhere('structure_id', $user->structure_id);
                }
                if ($roleNames !== []) {
                    $q->orWhereIn('role_name', $roleNames);
                }
            })
            ->exists();

        if ($exception) {
            return false;
        }

        return DocumentFolderAccessRule::query()
            ->whereIn('document_folder_id', $folderIds)
            ->where('ability', $ability->value)
            ->where('is_exception', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($user, $roleNames) {
                $q->where('user_id', $user->id);
                if ($user->structure_id) {
                    $q->orWhere('structure_id', $user->structure_id);
                }
                if ($roleNames !== []) {
                    $q->orWhereIn('role_name', $roleNames);
                }
            })
            ->exists();
    }

    /**
     * Bootstrap racine + dossiers par structure active.
     */
    public function bootstrapFromStructures(User $actor): void
    {
        $root = DocumentFolder::query()->firstOrCreate(
            ['code' => 'ROOT_DOC', 'parent_id' => null],
            [
                'name' => 'Documentation officielle',
                'description' => 'Bibliothèque documentaire institutionnelle DGTCP',
                'kind' => DocumentFolderKind::Institutional->value,
                'path' => '/tmp/',
                'depth' => 0,
                'default_publication_workflow' => PublicationWorkflow::Controlled->value,
                'sort_order' => 0,
            ]
        );
        if ($root->path === '/tmp/' || ! $root->path) {
            $root->rebuildPath();
        }

        $archives = DocumentFolder::query()->firstOrCreate(
            ['code' => 'ARCHIVES', 'parent_id' => $root->id],
            [
                'name' => 'Archives courantes',
                'kind' => DocumentFolderKind::Institutional->value,
                'path' => '/tmp/',
                'depth' => 0,
                'default_publication_workflow' => PublicationWorkflow::Controlled->value,
                'sort_order' => 900,
            ]
        );
        if ($archives->path === '/tmp/' || ! $archives->path) {
            $archives->rebuildPath();
        }

        Structure::query()->where('is_active', true)->orderBy('sort_order')->each(function (Structure $structure) use ($root, $actor) {
            $folder = DocumentFolder::query()->firstOrCreate(
                ['code' => 'STR_'.$structure->code, 'parent_id' => $root->id],
                [
                    'name' => $structure->name,
                    'structure_id' => $structure->id,
                    'kind' => DocumentFolderKind::Structure->value,
                    'path' => '/tmp/',
                    'depth' => 0,
                    'default_publication_workflow' => PublicationWorkflow::Hierarchical->value,
                    'sort_order' => (int) $structure->sort_order,
                ]
            );
            if ($folder->path === '/tmp/' || ! $folder->path) {
                $folder->rebuildPath();
            }

            if (! $folder->accessRules()->where('structure_id', $structure->id)->where('ability', 'view')->exists()) {
                $this->grantAccess(
                    $folder,
                    $actor,
                    DocumentAccessAbility::View,
                    structureId: $structure->id,
                );
                $this->grantAccess(
                    $folder,
                    $actor,
                    DocumentAccessAbility::Upload,
                    structureId: $structure->id,
                );
            }
        });
    }

    /**
     * @param  Collection<int, DocumentFolder>  $folders
     * @return Collection<int, DocumentFolder>
     */
    private function buildTree(Collection $folders, ?int $parentId = null): Collection
    {
        return $folders
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (DocumentFolder $folder) use ($folders) {
                $folder->setRelation('children', $this->buildTree($folders, $folder->id));

                return $folder;
            });
    }

    private function assertMoveDoesNotWidenAccess(DocumentFolder $folder, DocumentFolder $newParent): void
    {
        // Empêche de monter un dossier restreint sous un parent institutionnel ouvert
        // si le dossier source a des règles plus strictes que le parent cible.
        if ($folder->kind === DocumentFolderKind::Restricted
            && $newParent->kind === DocumentFolderKind::Institutional
            && $folder->accessRules()->exists()
            && ! $newParent->accessRules()->exists()) {
            throw new InvalidArgumentException(
                'Déplacement refusé : il exposerait des documents restreints sans contrôle d’accès équivalent.'
            );
        }

        $docCount = Document::query()->where('folder_id', $folder->id)
            ->whereIn('confidentiality', ['confidentiel', 'tres_confidentiel'])
            ->count();

        if ($docCount > 0 && $newParent->kind === DocumentFolderKind::Institutional && ! $newParent->accessRules()->exists()) {
            throw new InvalidArgumentException(
                'Déplacement refusé : des documents confidentiels seraient exposés dans un dossier trop ouvert.'
            );
        }
    }
}
