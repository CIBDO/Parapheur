<?php

namespace App\Services\Organization;

use App\Models\Structure;
use App\Models\StructureType;
use Illuminate\Support\Collection;

class StructureBoundaryService
{
    public function refreshAll(): void
    {
        /** @var Collection<int, Structure> $structures */
        $structures = Structure::query()->get()->keyBy('id');

        foreach ($structures as $structure) {
            $boundaryId = $this->resolveBoundaryId($structure, $structures);
            if ((int) $structure->boundary_structure_id !== $boundaryId) {
                $structure->boundary_structure_id = $boundaryId;
                $structure->saveQuietly();
            }
        }
    }

    public function markDefaultBoundaries(): void
    {
        $typeIds = StructureType::query()
            ->whereIn('code', ['DIR', 'TRESORERIE'])
            ->pluck('id');

        if ($typeIds->isNotEmpty()) {
            Structure::query()
                ->whereIn('structure_type_id', $typeIds)
                ->update(['is_access_boundary' => true]);
        }

        Structure::query()
            ->where('code', 'DG')
            ->update(['is_access_boundary' => true]);
    }

    /**
     * @param  Collection<int, Structure>  $byId
     */
    public function resolveBoundaryId(Structure $structure, Collection $byId): int
    {
        $current = $structure;
        $seen = [];

        while ($current) {
            if (isset($seen[$current->id])) {
                return $structure->id;
            }

            $seen[$current->id] = true;

            if ($current->is_access_boundary || $current->parent_id === null) {
                return $current->id;
            }

            $current = $current->parent_id ? $byId->get($current->parent_id) : null;
        }

        return $structure->id;
    }
}
