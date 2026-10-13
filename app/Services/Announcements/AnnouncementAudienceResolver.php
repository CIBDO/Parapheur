<?php

namespace App\Services\Announcements;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AnnouncementAudienceResolver
{
    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<int>
     */
    public function resolveIds(array $rules): array
    {
        $included = [];
        $excluded = [];

        foreach ($rules as $rule) {
            $ids = $this->idsForRule($rule);
            if (($rule['mode'] ?? 'include') === 'exclude') {
                $excluded = array_merge($excluded, $ids);
            } else {
                $included = array_merge($included, $ids);
            }
        }

        return array_values(array_diff(array_unique($included), array_unique($excluded)));
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return list<array{id: int, name: string}>
     */
    public function sample(array $rules, int $limit = 15): array
    {
        $ids = array_slice($this->resolveIds($rules), 0, $limit);
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<int>
     */
    private function idsForRule(array $rule): array
    {
        $query = User::query()->where('is_active', true);
        $type = (string) ($rule['target_type'] ?? '');

        return match ($type) {
            'all' => $query->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'user' => isset($rule['user_id']) ? [(int) $rule['user_id']] : [],
            'role' => $this->roleIds($query, $rule),
            'structure' => $this->structureIdsQuery($query, $rule),
            default => [],
        };
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $rule
     * @return list<int>
     */
    private function roleIds(Builder $query, array $rule): array
    {
        $role = trim((string) ($rule['role_name'] ?? ''));
        if ($role === '') {
            return [];
        }

        $query->role($role);
        if (! empty($rule['structure_id'])) {
            $query->whereIn('structure_id', $this->structureTree((int) $rule['structure_id'], (bool) ($rule['include_descendants'] ?? false)));
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $rule
     * @return list<int>
     */
    private function structureIdsQuery(Builder $query, array $rule): array
    {
        if (empty($rule['structure_id'])) {
            return [];
        }

        $ids = $this->structureTree((int) $rule['structure_id'], (bool) ($rule['include_descendants'] ?? false));

        return $query->whereIn('structure_id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> */
    public function structureTree(int $structureId, bool $withDescendants): array
    {
        $ids = [$structureId];
        if (! $withDescendants) {
            return $ids;
        }

        $frontier = [$structureId];
        while ($frontier !== []) {
            $children = Structure::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
