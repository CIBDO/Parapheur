<?php

namespace App\Services\Tasks;

use App\Enums\AccessScopeType;
use App\Models\Structure;
use App\Models\User;
use App\Services\Organization\OrganizationalContextResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Périmètre org pour les tâches : RBAC (verbes) + ABAC structure (données / annuaire).
 */
class TaskScopeResolver
{
    public function __construct(
        private readonly OrganizationalContextResolver $org,
    ) {}

    public function isGlobal(User $user): bool
    {
        return $user->can('task.view_all')
            || $user->can('task.manage')
            || $user->can('admin.access');
    }

    /**
     * Structures visibles / collaborables. Vide si global (pas de filtre structure).
     *
     * @return list<int>
     */
    public function structureIds(User $user): array
    {
        if ($this->isGlobal($user)) {
            return [];
        }

        $context = $this->org->resolve($user);

        if (! $context->synthetic && $context->structureIds !== []) {
            return array_values(array_unique(array_map('intval', $context->structureIds)));
        }

        $rootId = $user->structure_id ? (int) $user->structure_id : ($context->unitStructureId ?: $context->structureId);

        if (! $rootId) {
            return [];
        }

        // view_team sans affectation org explicite : périmètre hiérarchique (structure + descendants).
        if ($user->can('task.view_team') || $context->scope === AccessScopeType::Hierarchique) {
            return $this->subtreeIds($rootId);
        }

        if ($context->structureIds !== []) {
            return array_values(array_unique(array_map('intval', $context->structureIds)));
        }

        return [$rootId];
    }

    public function canCollaborateWith(User $actor, User $other): bool
    {
        if ($this->isGlobal($actor)) {
            return true;
        }

        if ((int) $actor->id === (int) $other->id) {
            return true;
        }

        $ids = $this->structureIds($actor);

        if ($ids === []) {
            return false;
        }

        return $other->structure_id !== null
            && in_array((int) $other->structure_id, $ids, true);
    }

    public function appliesToStructure(User $user, ?int $structureId): bool
    {
        if ($this->isGlobal($user)) {
            return true;
        }

        if (! $structureId) {
            return false;
        }

        return in_array((int) $structureId, $this->structureIds($user), true);
    }

    /**
     * Filtre un query builder User sur le périmètre de collaboration tâches.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function constrainUsersQuery(Builder $query, User $actor): Builder
    {
        if ($this->isGlobal($actor)) {
            return $query;
        }

        $ids = $this->structureIds($actor);

        if ($ids === []) {
            return $query->where('id', $actor->id);
        }

        return $query->where(function (Builder $q) use ($actor, $ids) {
            $q->whereIn('structure_id', $ids)
                ->orWhere('id', $actor->id);
        });
    }

    /**
     * @return list<int>
     */
    private function subtreeIds(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];

        for ($depth = 0; $depth < 30 && $frontier !== []; $depth++) {
            $children = Structure::query()
                ->whereIn('parent_id', $frontier)
                ->where('is_access_boundary', false)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $ids));

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }
}
