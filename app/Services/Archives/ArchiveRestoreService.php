<?php

namespace App\Services\Archives;

use App\Models\ArchiveIntegrityCheck;
use App\Models\ArchivePackage;
use App\Models\ArchivePreservationEvent;
use App\Models\ArchiveRecord;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ArchiveRestoreService
{
    public function __construct(
        private readonly ArchiveIntegrityService $integrity,
        private readonly ArchiveAuditService $audit,
    ) {}

    public function restorePackageFromReplica(User $actor, ArchivePackage $package): ArchivePackage
    {
        if (! $package->replica_disk || ! $package->replica_path) {
            throw new InvalidArgumentException('Aucune réplique disponible pour ce paquet.');
        }

        $replica = Storage::disk($package->replica_disk);
        if (! $replica->exists($package->replica_path)) {
            throw new InvalidArgumentException('Fichier réplique introuvable.');
        }

        $primaryDisk = $package->disk ?: 'archives';
        $primary = Storage::disk($primaryDisk);
        $targetPath = $package->path;

        if (! $targetPath) {
            throw new InvalidArgumentException('Chemin primaire du paquet manquant.');
        }

        $bytes = $replica->get($package->replica_path);
        $checksum = hash('sha256', $bytes);

        if ($package->package_checksum && ! hash_equals((string) $package->package_checksum, $checksum)) {
            throw new InvalidArgumentException('L’empreinte de la réplique ne correspond pas au manifeste.');
        }

        // Restauration contrôlée : écrasement autorisé uniquement pour réparation.
        $primary->put($targetPath, $bytes);

        $package->fill([
            'package_checksum' => $checksum,
            'size_bytes' => strlen($bytes),
            'status' => 'restored',
        ])->save();

        if ($package->record_id) {
            $record = ArchiveRecord::query()->find($package->record_id);
            if ($record) {
                $record->preservation_status = 'active';
                $record->last_integrity_result = 'restored';
                $record->save();
            }
        }

        ArchivePreservationEvent::query()->create([
            'record_id' => $package->record_id,
            'package_id' => $package->id,
            'event_type' => 'restoration',
            'actor_id' => $actor->id,
            'payload' => [
                'from_disk' => $package->replica_disk,
                'from_path' => $package->replica_path,
                'to_disk' => $primaryDisk,
                'to_path' => $targetPath,
                'checksum' => $checksum,
            ],
            'occurred_at' => now(),
        ]);

        ArchiveIntegrityCheck::query()->create([
            'scope' => 'package',
            'package_id' => $package->id,
            'record_id' => $package->record_id,
            'checked_by' => $actor->id,
            'result' => 'restored',
            'expected_checksum' => $package->package_checksum,
            'actual_checksum' => $checksum,
            'message' => 'Restauration depuis réplique',
            'checked_at' => now(),
        ]);

        $this->audit->log('package.restored_from_replica', $package, $actor, [
            'checksum' => $checksum,
        ]);

        $fresh = $package->fresh();
        $this->integrity->verifyPackage($actor, $fresh);

        return $fresh->fresh();
    }

    public function restoreRecordFromReplica(User $actor, ArchiveRecord $record): ArchivePackage
    {
        $package = ArchivePackage::query()
            ->where('record_id', $record->id)
            ->whereNotNull('replica_path')
            ->orderByDesc('id')
            ->first();

        if (! $package) {
            throw new InvalidArgumentException('Aucun paquet répliqué pour cette archive.');
        }

        return $this->restorePackageFromReplica($actor, $package);
    }
}
