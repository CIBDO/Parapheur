<?php

namespace App\Services\Archives;

use App\Models\ArchiveMovement;
use App\Models\User;
use Illuminate\Support\Str;

class ArchiveMovementService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    /**
     * @param  array{
     *   movement_type: string,
     *   box_id?: int|null,
     *   record_id?: int|null,
     *   from_location_id?: int|null,
     *   to_location_id?: int|null,
     *   structure_id: int,
     *   motif?: string|null,
     *   notes?: string|null,
     *   validated_by?: int|null,
     * }  $data
     */
    public function record(User $actor, array $data): ArchiveMovement
    {
        $movement = ArchiveMovement::query()->create([
            'reference' => 'MV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'movement_type' => $data['movement_type'],
            'box_id' => $data['box_id'] ?? null,
            'record_id' => $data['record_id'] ?? null,
            'from_location_id' => $data['from_location_id'] ?? null,
            'to_location_id' => $data['to_location_id'] ?? null,
            'structure_id' => (int) $data['structure_id'],
            'actor_id' => $actor->id,
            'validated_by' => $data['validated_by'] ?? null,
            'motif' => $data['motif'] ?? null,
            'notes' => $data['notes'] ?? null,
            'moved_at' => now(),
        ]);

        $this->audit->log('movement.recorded', $movement, $actor, [
            'type' => $movement->movement_type,
            'box_id' => $movement->box_id,
        ]);

        return $movement->load(['box', 'fromLocation', 'toLocation', 'actor']);
    }
}
