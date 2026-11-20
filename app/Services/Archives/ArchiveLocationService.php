<?php

namespace App\Services\Archives;

use App\Enums\ArchiveLocationType;
use App\Models\ArchiveStorageLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArchiveLocationService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ArchiveStorageLocation
    {
        return DB::transaction(function () use ($actor, $data) {
            $parent = null;
            $fullCode = strtoupper(trim((string) $data['code']));

            if (! empty($data['parent_id'])) {
                $parent = ArchiveStorageLocation::query()->findOrFail((int) $data['parent_id']);
                if ((int) $parent->structure_id !== (int) $data['structure_id']) {
                    throw new InvalidArgumentException('Le parent doit appartenir à la même structure.');
                }
                $fullCode = rtrim($parent->full_code, '/').'/'.strtoupper(trim((string) $data['code']));
            }

            $type = ArchiveLocationType::from((string) $data['type']);

            $location = ArchiveStorageLocation::query()->create([
                'parent_id' => $parent?->id,
                'structure_id' => (int) $data['structure_id'],
                'type' => $type,
                'code' => strtoupper(trim((string) $data['code'])),
                'name' => $data['name'],
                'full_code' => $fullCode,
                'status' => $data['status'] ?? 'disponible',
                'capacity_linear_meters' => $data['capacity_linear_meters'] ?? null,
                'capacity_boxes' => $data['capacity_boxes'] ?? null,
                'width_cm' => $data['width_cm'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'depth_cm' => $data['depth_cm'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log('location.created', $location, $actor, [
                'full_code' => $location->full_code,
                'type' => $location->type->value,
            ]);

            return $location->load(['parent', 'structure']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ArchiveStorageLocation $location, array $data): ArchiveStorageLocation
    {
        $location->fill([
            'name' => $data['name'] ?? $location->name,
            'status' => $data['status'] ?? $location->status,
            'capacity_linear_meters' => $data['capacity_linear_meters'] ?? $location->capacity_linear_meters,
            'capacity_boxes' => $data['capacity_boxes'] ?? $location->capacity_boxes,
            'width_cm' => $data['width_cm'] ?? $location->width_cm,
            'height_cm' => $data['height_cm'] ?? $location->height_cm,
            'depth_cm' => $data['depth_cm'] ?? $location->depth_cm,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $location->is_active,
            'notes' => $data['notes'] ?? $location->notes,
            'sort_order' => $data['sort_order'] ?? $location->sort_order,
        ])->save();

        $this->audit->log('location.updated', $location, $actor);

        return $location->fresh(['parent', 'structure', 'children']);
    }

    public function delete(User $actor, ArchiveStorageLocation $location): void
    {
        if ($location->children()->exists() || $location->boxes()->exists()) {
            throw new InvalidArgumentException('Impossible de supprimer un emplacement ayant des enfants ou des boîtes.');
        }

        $this->audit->log('location.deleted', $location, $actor);
        $location->delete();
    }

    public function assertCapacity(ArchiveStorageLocation $location, bool $allowOverride = false): void
    {
        if ($allowOverride) {
            return;
        }

        if ($location->capacity_boxes !== null && $location->occupied_boxes >= $location->capacity_boxes) {
            throw new InvalidArgumentException('Capacité boîtes saturée pour '.$location->full_code.'.');
        }

        if ($location->capacity_linear_meters !== null
            && (float) $location->occupied_linear_meters >= (float) $location->capacity_linear_meters) {
            throw new InvalidArgumentException('Capacité linéaire saturée pour '.$location->full_code.'.');
        }
    }

    public function refreshOccupation(ArchiveStorageLocation $location): void
    {
        $boxes = $location->boxes()->where('status', 'en_magasin')->get();
        $location->occupied_boxes = $boxes->count();
        $location->occupied_linear_meters = round((float) $boxes->sum('linear_meters'), 2);
        $location->status = ($location->capacity_boxes !== null && $location->occupied_boxes >= $location->capacity_boxes)
            ? 'occupe'
            : ($location->status === 'indisponible' || $location->status === 'quarantaine' ? $location->status : 'disponible');
        $location->save();
    }
}
