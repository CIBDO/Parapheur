<?php

namespace App\Services\Archives;

use App\Models\ArchiveIntegrityCheck;
use App\Models\ArchiveLoan;
use App\Models\ArchivePackage;
use App\Models\ArchivePreservationEvent;
use App\Models\ArchiveRecord;
use App\Models\ArchiveStorageLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ArchivePreservationService
{
    public function __construct(
        private readonly ArchivePackageService $packages,
        private readonly ArchiveIntegrityService $integrity,
        private readonly ArchiveAuditService $audit,
    ) {}

    public function sealRecord(User $actor, ArchiveRecord $record, bool $replicate = true): ArchivePackage
    {
        $package = $this->packages->buildAipForRecord($actor, $record);
        $this->integrity->verifyPackage($actor, $package);

        if ($replicate) {
            $package = $this->packages->replicate($actor, $package);
        }

        return $package->fresh(['items']);
    }

    /**
     * Planifie une migration de format et déclenche le job (Ghostscript si disponible).
     */
    public function registerFormatMigration(User $actor, ArchiveRecord $record, string $targetProfile = 'pdfa'): ArchivePreservationEvent
    {
        $record->preservation_status = 'migration_planned';
        $record->save();

        $event = ArchivePreservationEvent::query()->create([
            'record_id' => $record->id,
            'event_type' => 'format_migration',
            'actor_id' => $actor->id,
            'payload' => [
                'target_profile' => $targetProfile,
                'status' => 'queued',
                'note' => 'Job de migration dispatché.',
            ],
            'occurred_at' => now(),
        ]);

        $this->audit->log('preservation.format_migration_queued', $record, $actor, [
            'target_profile' => $targetProfile,
        ]);

        if (config('archives.format_migration.enabled', true)) {
            \App\Jobs\MigrateArchiveFormatJob::dispatch($record->id, $actor->id, $targetProfile);
        }

        return $event;
    }

    public function restitutionPackage(User $actor, ArchiveRecord $record): ArchivePackage
    {
        $package = $this->packages->buildDipForRecord($actor, $record);

        ArchivePreservationEvent::query()->create([
            'record_id' => $record->id,
            'package_id' => $package->id,
            'event_type' => 'restitution',
            'actor_id' => $actor->id,
            'payload' => [
                'reference' => $package->reference,
                'path' => $package->path,
            ],
            'occurred_at' => now(),
        ]);

        $this->audit->log('preservation.restitution', $record, $actor, [
            'package_id' => $package->id,
        ]);

        return $package;
    }

    /**
     * @return array<string, mixed>
     */
    public function supervisionSnapshot(): array
    {
        return [
            'integrity_failures' => DB::table('archive_integrity_checks')
                ->whereIn('result', ['mismatch', 'missing', 'error'])
                ->where('checked_at', '>=', now()->subDays(30))
                ->count(),
            'records_at_risk' => ArchiveRecord::query()->where('preservation_status', 'at_risk')->count(),
            'packages_sealed' => ArchivePackage::query()->whereIn('status', ['sealed', 'replicated'])->count(),
            'packages_replicated' => ArchivePackage::query()->where('status', 'replicated')->count(),
            'locations_saturated' => ArchiveStorageLocation::query()
                ->whereNotNull('capacity_boxes')
                ->whereColumn('occupied_boxes', '>=', 'capacity_boxes')
                ->count(),
            'loans_overdue' => ArchiveLoan::query()
                ->where(function ($q) {
                    $q->where('status', 'en_retard')
                        ->orWhere(function ($q2) {
                            $q2->where('status', 'sorti')->whereDate('due_at', '<', now()->toDateString());
                        });
                })
                ->count(),
            'recent_failures' => ArchiveIntegrityCheck::query()
                ->with(['record:id,permanent_id,title'])
                ->whereIn('result', ['mismatch', 'missing', 'error'])
                ->orderByDesc('checked_at')
                ->limit(10)
                ->get(),
        ];
    }

    public function absolutePackagePath(ArchivePackage $package): string
    {
        if (! $package->path) {
            throw new InvalidArgumentException('Paquet sans chemin.');
        }

        if (! Storage::disk($package->disk)->exists($package->path)) {
            throw new InvalidArgumentException('Fichier paquet introuvable.');
        }

        return Storage::disk($package->disk)->path($package->path);
    }
}
