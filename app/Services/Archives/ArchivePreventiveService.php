<?php

namespace App\Services\Archives;

use App\Models\ArchiveClimateReading;
use App\Models\ArchivePreventiveEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ArchivePreventiveService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    /**
     * @param  array{
     *   structure_id: int,
     *   location_id?: int|null,
     *   box_id?: int|null,
     *   event_type: string,
     *   severity?: string,
     *   title: string,
     *   description?: string|null,
     *   occurred_at?: string|null,
     *   payload?: array<string, mixed>|null,
     * }  $data
     */
    public function reportEvent(User $actor, array $data): ArchivePreventiveEvent
    {
        $event = ArchivePreventiveEvent::query()->create([
            'structure_id' => (int) $data['structure_id'],
            'location_id' => $data['location_id'] ?? null,
            'box_id' => $data['box_id'] ?? null,
            'event_type' => $data['event_type'],
            'severity' => $data['severity'] ?? 'info',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'reported_by' => $actor->id,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'payload' => $data['payload'] ?? null,
        ]);

        $this->audit->log('preventive.event_reported', $event, $actor, [
            'type' => $event->event_type,
            'severity' => $event->severity,
        ]);

        return $event->fresh(['location', 'box', 'reporter']);
    }

    /**
     * @param  array{
     *   structure_id: int,
     *   location_id: int,
     *   temperature_c?: float|null,
     *   humidity_pct?: float|null,
     *   measured_at?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public function recordClimate(User $actor, array $data): ArchiveClimateReading
    {
        $reading = ArchiveClimateReading::query()->create([
            'structure_id' => (int) $data['structure_id'],
            'location_id' => (int) $data['location_id'],
            'temperature_c' => $data['temperature_c'] ?? null,
            'humidity_pct' => $data['humidity_pct'] ?? null,
            'source' => 'manual',
            'recorded_by' => $actor->id,
            'measured_at' => $data['measured_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ]);

        $alerts = $this->climateAlerts($reading);
        if ($alerts !== []) {
            $this->reportEvent($actor, [
                'structure_id' => $reading->structure_id,
                'location_id' => $reading->location_id,
                'event_type' => 'climat',
                'severity' => 'warning',
                'title' => 'Anomalie climatique',
                'description' => implode(' ; ', $alerts),
                'occurred_at' => $reading->measured_at,
                'payload' => [
                    'temperature_c' => $reading->temperature_c,
                    'humidity_pct' => $reading->humidity_pct,
                ],
            ]);
        }

        $this->audit->log('preventive.climate_recorded', $reading, $actor);

        return $reading->fresh(['location', 'recorder']);
    }

    public function listEvents(int $structureId, int $perPage = 20): LengthAwarePaginator
    {
        return ArchivePreventiveEvent::query()
            ->with(['location', 'box', 'reporter'])
            ->where('structure_id', $structureId)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);
    }

    public function listClimate(int $structureId, int $perPage = 20): LengthAwarePaginator
    {
        return ArchiveClimateReading::query()
            ->with(['location', 'recorder'])
            ->where('structure_id', $structureId)
            ->orderByDesc('measured_at')
            ->paginate($perPage);
    }

    /**
     * @return list<string>
     */
    private function climateAlerts(ArchiveClimateReading $reading): array
    {
        $alerts = [];
        $tMin = (float) config('archives.climate.temperature_min');
        $tMax = (float) config('archives.climate.temperature_max');
        $hMin = (float) config('archives.climate.humidity_min');
        $hMax = (float) config('archives.climate.humidity_max');

        if ($reading->temperature_c !== null && ($reading->temperature_c < $tMin || $reading->temperature_c > $tMax)) {
            $alerts[] = "Température hors plage ({$reading->temperature_c}°C, attendu {$tMin}-{$tMax})";
        }
        if ($reading->humidity_pct !== null && ($reading->humidity_pct < $hMin || $reading->humidity_pct > $hMax)) {
            $alerts[] = "Humidité hors plage ({$reading->humidity_pct}%, attendu {$hMin}-{$hMax})";
        }

        return $alerts;
    }
}
