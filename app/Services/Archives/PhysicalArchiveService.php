<?php

namespace App\Services\Archives;

use App\Models\ArchiveBox;
use App\Models\ArchiveBoxContent;
use App\Models\ArchiveRecord;
use App\Models\ArchiveStorageLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PhysicalArchiveService
{
    public function __construct(
        private readonly ArchiveAuditService $audit,
        private readonly ArchiveLocationService $locations,
        private readonly ArchiveMovementService $movements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createBox(User $actor, array $data): ArchiveBox
    {
        return DB::transaction(function () use ($actor, $data) {
            $location = null;
            if (! empty($data['location_id'])) {
                $location = ArchiveStorageLocation::query()->findOrFail((int) $data['location_id']);
                $this->locations->assertCapacity($location, (bool) ($data['force_capacity'] ?? false));
            }

            $box = ArchiveBox::query()->create([
                'code' => strtoupper(trim((string) ($data['code'] ?? $this->nextBoxCode()))),
                'label' => $data['label'] ?? null,
                'structure_id' => (int) $data['structure_id'],
                'producer_unit_id' => $data['producer_unit_id'] ?? null,
                'fonds_id' => $data['fonds_id'] ?? null,
                'series_id' => $data['series_id'] ?? null,
                'location_id' => $location?->id,
                'typology' => $data['typology'] ?? null,
                'physical_state' => $data['physical_state'] ?? 'bon',
                'status' => $location ? 'en_magasin' : ($data['status'] ?? 'en_magasin'),
                'date_start' => $data['date_start'] ?? null,
                'date_end' => $data['date_end'] ?? null,
                'linear_meters' => $data['linear_meters'] ?? null,
                'width_cm' => $data['width_cm'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'depth_cm' => $data['depth_cm'] ?? null,
                'responsible_id' => $data['responsible_id'] ?? $actor->id,
                'deposited_at' => $data['deposited_at'] ?? now()->toDateString(),
                'retention_years' => $data['retention_years'] ?? null,
                'final_disposition' => $data['final_disposition'] ?? null,
                'qr_token' => Str::lower(Str::random(40)),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($location) {
                $this->movements->record($actor, [
                    'movement_type' => 'entree',
                    'box_id' => $box->id,
                    'to_location_id' => $location->id,
                    'structure_id' => $box->structure_id,
                    'motif' => 'Entrée en magasin',
                ]);
                $this->locations->refreshOccupation($location);
            }

            $this->audit->log('box.created', $box, $actor, ['code' => $box->code]);

            return $box->load(['location', 'structure', 'fonds', 'series']);
        });
    }

    public function assignLocation(User $actor, ArchiveBox $box, int $locationId, bool $force = false, ?string $motif = null): ArchiveBox
    {
        return DB::transaction(function () use ($actor, $box, $locationId, $force, $motif) {
            $to = ArchiveStorageLocation::query()->findOrFail($locationId);
            if ((int) $to->structure_id !== (int) $box->structure_id) {
                throw new InvalidArgumentException('Emplacement hors structure de la boîte.');
            }

            $fromId = $box->location_id;
            if ($fromId !== $to->id) {
                $this->locations->assertCapacity($to, $force);
            }

            $box->location_id = $to->id;
            $box->status = 'en_magasin';
            $box->save();

            $this->movements->record($actor, [
                'movement_type' => $fromId ? 'deplacement' : 'affectation',
                'box_id' => $box->id,
                'from_location_id' => $fromId,
                'to_location_id' => $to->id,
                'structure_id' => $box->structure_id,
                'motif' => $motif ?: 'Affectation d’emplacement',
            ]);

            if ($fromId) {
                $from = ArchiveStorageLocation::query()->find($fromId);
                if ($from) {
                    $this->locations->refreshOccupation($from);
                }
            }
            $this->locations->refreshOccupation($to);

            $this->audit->log('box.relocated', $box, $actor, [
                'from_location_id' => $fromId,
                'to_location_id' => $to->id,
            ]);

            return $box->fresh(['location', 'structure']);
        });
    }

    public function addContent(User $actor, ArchiveBox $box, ?int $recordId, array $meta = []): ArchiveBoxContent
    {
        return DB::transaction(function () use ($actor, $box, $recordId, $meta) {
            $record = null;
            if ($recordId) {
                $record = ArchiveRecord::query()->findOrFail($recordId);
                $record->box_id = $box->id;
                $record->location_id = $box->location_id;
                $record->save();
            }

            $content = ArchiveBoxContent::query()->create([
                'box_id' => $box->id,
                'record_id' => $record?->id,
                'folder_code' => $meta['folder_code'] ?? null,
                'title' => $meta['title'] ?? $record?->title,
                'date_start' => $meta['date_start'] ?? $record?->date_start,
                'date_end' => $meta['date_end'] ?? $record?->date_end,
                'sort_order' => $meta['sort_order'] ?? 0,
                'notes' => $meta['notes'] ?? null,
            ]);

            $box->folder_count = $box->contents()->count();
            $box->save();

            $this->audit->log('box.content_added', $box, $actor, [
                'content_id' => $content->id,
                'record_id' => $record?->id,
            ]);

            return $content->load('record');
        });
    }

    public function resolveByQrToken(string $token): ?ArchiveBox
    {
        return ArchiveBox::query()
            ->with(['location', 'structure', 'contents.record'])
            ->where('qr_token', $token)
            ->first();
    }

    private function nextBoxCode(): string
    {
        return 'BX-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }
}
