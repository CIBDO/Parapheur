<?php

namespace App\Services\Organization;

use App\Enums\AccessScopeType;
use App\Models\OrganizationalRoleAssignment;
use App\Models\Structure;
use App\Models\User;
use App\Models\UserWorkContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class OrganizationalContextResolver
{
    /**
     * @var array<int, OrganizationalContext>
     */
    private array $cache = [];

    public function resolve(User $user): OrganizationalContext
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        $assignments = $this->activeAssignments($user);

        if ($assignments->isEmpty()) {
            $hadAssignment = OrganizationalRoleAssignment::query()
                ->where('user_id', $user->id)
                ->exists();

            if ($hadAssignment) {
                return $this->cache[$user->id] = new OrganizationalContext(
                    assignmentId: null,
                    scope: AccessScopeType::Personnel,
                    structureId: null,
                    unitStructureId: null,
                    structureIds: [],
                    synthetic: false,
                );
            }

            return $this->cache[$user->id] = $this->synthetic($user);
        }

        $stored = UserWorkContext::query()->where('user_id', $user->id)->first();
        $selected = $assignments->firstWhere('id', $stored?->organizational_role_assignment_id)
            ?? $assignments->first();

        return $this->cache[$user->id] = $this->fromAssignment($selected);
    }

    public function forget(User $user): void
    {
        unset($this->cache[$user->id]);
    }

    /**
     * @return array{current: array<string, mixed>, options: list<array<string, mixed>>}
     */
    public function present(User $user): array
    {
        $current = $this->resolve($user);
        $assignments = $this->activeAssignments($user);

        $options = $assignments->isEmpty()
            ? [$this->presentContext($current, null)]
            : $assignments->map(fn (OrganizationalRoleAssignment $assignment) => $this->presentContext(
                $this->fromAssignment($assignment),
                $assignment
            ))->values()->all();

        return [
            'current' => $this->presentContext($current, $assignments->firstWhere('id', $current->assignmentId)),
            'options' => $options,
        ];
    }

    public function switch(User $user, int $assignmentId): OrganizationalContext
    {
        $assignment = $this->activeAssignments($user)->firstWhere('id', $assignmentId);

        if (! $assignment) {
            throw new AuthorizationException('Cette habilitation n’est pas active pour votre compte.');
        }

        UserWorkContext::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'organizational_role_assignment_id' => $assignment->id,
                'structure_id' => $assignment->structure_id,
                'unit_structure_id' => $assignment->unit_structure_id,
            ]
        );

        $this->forget($user);

        return $this->resolve($user);
    }

    public function allowsPermission(User $user, string $permission): bool
    {
        $context = $this->resolve($user);

        if ($context->synthetic || $context->assignmentId === null) {
            return $user->can($permission);
        }

        $assignment = OrganizationalRoleAssignment::query()
            ->with('role')
            ->find($context->assignmentId);

        return (bool) $assignment?->role?->hasPermissionTo($permission);
    }

    public function resolveOwnerStructureId(User $user, ?int $requested): ?int
    {
        $context = $this->resolve($user);
        $allowed = $context->structureIds;

        if ($requested === null || $requested === 0) {
            if ($user->structure_id && in_array((int) $user->structure_id, $allowed, true)) {
                return (int) $user->structure_id;
            }

            return $context->structureId ?? $user->structure_id;
        }

        if (! in_array($requested, $allowed, true)) {
            throw new AuthorizationException('Cette structure est hors de votre périmètre.');
        }

        return $requested;
    }

    /**
     * @return Collection<int, OrganizationalRoleAssignment>
     */
    private function activeAssignments(User $user): Collection
    {
        return OrganizationalRoleAssignment::query()
            ->with(['structure:id,code,name', 'unit:id,code,name'])
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('id')
            ->get();
    }

    private function synthetic(User $user): OrganizationalContext
    {
        $structureId = $user->structure_id ? (int) $user->structure_id : null;
        $boundaryId = $structureId
            ? (int) (Structure::query()->whereKey($structureId)->value('boundary_structure_id') ?: $structureId)
            : null;

        $scope = $user->can('mail.view_all')
            ? AccessScopeType::Structure
            : AccessScopeType::Unite;

        $ids = match ($scope) {
            AccessScopeType::Structure => $this->idsForBoundary($boundaryId),
            default => $structureId ? [$structureId] : [],
        };

        return new OrganizationalContext(
            assignmentId: null,
            scope: $scope,
            structureId: $boundaryId,
            unitStructureId: $structureId,
            structureIds: $ids,
            synthetic: true,
        );
    }

    private function fromAssignment(OrganizationalRoleAssignment $assignment): OrganizationalContext
    {
        $scope = $assignment->scope_type instanceof AccessScopeType
            ? $assignment->scope_type
            : AccessScopeType::from((string) $assignment->scope_type);

        $boundaryId = (int) $assignment->structure_id;
        $unitId = $assignment->unit_structure_id ? (int) $assignment->unit_structure_id : null;

        $ids = match ($scope) {
            AccessScopeType::Personnel, AccessScopeType::Specifique => [],
            AccessScopeType::Unite => [$unitId ?? $boundaryId],
            AccessScopeType::Hierarchique => $this->subtreeIds($unitId ?? $boundaryId),
            AccessScopeType::Structure, AccessScopeType::Transversal => $this->idsForBoundary($boundaryId),
        };

        return new OrganizationalContext(
            assignmentId: $assignment->id,
            scope: $scope,
            structureId: $boundaryId,
            unitStructureId: $unitId,
            structureIds: $ids,
            synthetic: false,
        );
    }

    /**
     * @return list<int>
     */
    private function idsForBoundary(?int $boundaryId): array
    {
        if (! $boundaryId) {
            return [];
        }

        return Structure::query()
            ->where('boundary_structure_id', $boundaryId)
            ->orWhere('id', $boundaryId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Descendants couverts, sans traverser une autre frontière d'accès.
     *
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

    /**
     * @return array<string, mixed>
     */
    private function presentContext(OrganizationalContext $context, ?OrganizationalRoleAssignment $assignment): array
    {
        $structure = $assignment?->structure;
        if (! $structure && $context->structureId) {
            $structure = Structure::query()->find($context->structureId);
        }

        $unit = $assignment?->unit;
        if (! $unit && $context->unitStructureId && $context->unitStructureId !== $context->structureId) {
            $unit = Structure::query()->find($context->unitStructureId);
        }

        return [
            'assignment_id' => $context->assignmentId,
            'scope' => $context->scope->value,
            'scope_label' => $context->scope->label(),
            'structure_id' => $context->structureId,
            'unit_structure_id' => $context->unitStructureId,
            'structure' => $structure?->only(['id', 'code', 'name']),
            'unit' => $unit?->only(['id', 'code', 'name']),
            'synthetic' => $context->synthetic,
        ];
    }
}
