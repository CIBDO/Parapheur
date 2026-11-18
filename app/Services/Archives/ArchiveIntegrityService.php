<?php

namespace App\Services\Archives;

use App\Models\ArchiveIntegrityCheck;
use App\Models\ArchivePackage;
use App\Models\ArchivePreservationEvent;
use App\Models\ArchiveRecord;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ArchiveIntegrityService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    public function verifyRecord(?User $actor, ArchiveRecord $record): ArchiveIntegrityCheck
    {
        $record->load(['documentVersion', 'document.officialVersion', 'document.latestVersion']);
        $version = $record->documentVersion
            ?: ($record->document?->officialVersion ?: $record->document?->latestVersion);

        $expected = $record->checksum ?: $version?->checksum;
        $actual = null;
        $result = 'error';
        $message = null;

        if (! $version || ! $version->disk || ! $version->path) {
            $result = 'missing';
            $message = 'Aucune version fichier associée.';
        } elseif (! Storage::disk($version->disk)->exists($version->path)) {
            $result = 'missing';
            $message = 'Fichier absent du stockage '.$version->disk.':'.$version->path;
        } else {
            $absolute = Storage::disk($version->disk)->path($version->path);
            $actual = is_file($absolute) ? hash_file('sha256', $absolute) : null;
            if (! $expected) {
                $result = 'ok';
                $message = 'Empreinte absente en base ; empreinte calculée enregistrée.';
                $record->checksum = $actual;
            } elseif ($actual && hash_equals($expected, $actual)) {
                $result = 'ok';
                $message = 'Empreinte conforme.';
            } else {
                $result = 'mismatch';
                $message = 'Empreinte divergente.';
            }
        }

        $check = ArchiveIntegrityCheck::query()->create([
            'scope' => 'record',
            'record_id' => $record->id,
            'document_version_id' => $version?->id,
            'expected_checksum' => $expected,
            'actual_checksum' => $actual,
            'result' => $result,
            'message' => $message,
            'checked_by' => $actor?->id,
            'checked_at' => now(),
        ]);

        $record->fill([
            'last_integrity_at' => now(),
            'last_integrity_result' => $result,
            'preservation_status' => $result === 'ok' ? 'verified' : 'at_risk',
        ])->save();

        ArchivePreservationEvent::query()->create([
            'record_id' => $record->id,
            'event_type' => $result === 'ok' ? 'integrity_ok' : 'integrity_fail',
            'actor_id' => $actor?->id,
            'payload' => [
                'check_id' => $check->id,
                'result' => $result,
            ],
            'occurred_at' => now(),
        ]);

        if ($actor) {
            $this->audit->log('integrity.checked', $record, $actor, [
                'result' => $result,
            ]);
        }

        return $check;
    }

    public function verifyPackage(?User $actor, ArchivePackage $package): ArchiveIntegrityCheck
    {
        $expected = $package->package_checksum;
        $actual = null;
        $result = 'error';
        $message = null;

        if (! $package->path || ! Storage::disk($package->disk)->exists($package->path)) {
            $result = 'missing';
            $message = 'Paquet absent du disque '.$package->disk;
        } else {
            $absolute = Storage::disk($package->disk)->path($package->path);
            $actual = is_file($absolute) ? hash_file('sha256', $absolute) : null;
            if ($expected && $actual && hash_equals($expected, $actual)) {
                $result = 'ok';
                $message = 'Paquet intègre.';
            } elseif (! $expected && $actual) {
                $result = 'ok';
                $package->package_checksum = $actual;
                $package->save();
                $message = 'Checksum paquet calculé.';
            } else {
                $result = 'mismatch';
                $message = 'Checksum paquet divergent.';
            }
        }

        $check = ArchiveIntegrityCheck::query()->create([
            'scope' => 'package',
            'package_id' => $package->id,
            'record_id' => $package->record_id,
            'expected_checksum' => $expected,
            'actual_checksum' => $actual,
            'result' => $result,
            'message' => $message,
            'checked_by' => $actor?->id,
            'checked_at' => now(),
        ]);

        ArchivePreservationEvent::query()->create([
            'record_id' => $package->record_id,
            'package_id' => $package->id,
            'event_type' => $result === 'ok' ? 'integrity_ok' : 'integrity_fail',
            'actor_id' => $actor?->id,
            'payload' => ['check_id' => $check->id, 'result' => $result],
            'occurred_at' => now(),
        ]);

        return $check;
    }

    /**
     * @return array{checked: int, ok: int, failed: int, checks: list<ArchiveIntegrityCheck>}
     */
    public function verifyBatch(?User $actor = null, int $limit = 100): array
    {
        $records = ArchiveRecord::query()
            ->whereIn('status', ['conserve', 'gele', 'a_eliminer'])
            ->orderBy('last_integrity_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $checks = [];
        $ok = 0;
        $failed = 0;

        foreach ($records as $record) {
            $check = $this->verifyRecord($actor, $record);
            $checks[] = $check;
            if ($check->result === 'ok') {
                $ok++;
            } else {
                $failed++;
            }
        }

        return [
            'checked' => count($checks),
            'ok' => $ok,
            'failed' => $failed,
            'checks' => $checks,
        ];
    }
}
