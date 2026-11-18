<?php

namespace App\Services\Archives;

use App\Enums\ArchivePackageType;
use App\Models\ArchivePackage;
use App\Models\ArchivePackageItem;
use App\Models\ArchivePreservationEvent;
use App\Models\ArchiveRecord;
use App\Models\ArchiveTransfer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

class ArchivePackageService
{
    public function __construct(private readonly ArchiveAuditService $audit) {}

    public function buildSipFromTransfer(User $actor, ArchiveTransfer $transfer): ArchivePackage
    {
        $transfer->load(['items.documentVersion', 'items.document', 'structure', 'records']);

        $records = $transfer->records;
        if ($records->isEmpty()) {
            throw new InvalidArgumentException('Le versement doit être conservé avant génération SIP/AIP.');
        }

        return $this->buildPackage($actor, ArchivePackageType::Sip, [
            'structure_id' => $transfer->structure_id,
            'transfer_id' => $transfer->id,
            'records' => $records->all(),
            'notes' => 'SIP généré depuis versement '.$transfer->reference,
        ]);
    }

    public function buildAipForRecord(User $actor, ArchiveRecord $record): ArchivePackage
    {
        $record->load(['document.officialVersion', 'document.latestVersion', 'documentVersion', 'structure']);

        return $this->buildPackage($actor, ArchivePackageType::Aip, [
            'structure_id' => $record->structure_id,
            'record_id' => $record->id,
            'records' => [$record],
            'notes' => 'AIP de préservation '.$record->permanent_id,
            'immutable' => true,
        ]);
    }

    public function buildDipForRecord(User $actor, ArchiveRecord $record): ArchivePackage
    {
        $record->load(['document.officialVersion', 'document.latestVersion', 'documentVersion', 'structure']);

        return $this->buildPackage($actor, ArchivePackageType::Dip, [
            'structure_id' => $record->structure_id,
            'record_id' => $record->id,
            'records' => [$record],
            'notes' => 'DIP de restitution '.$record->permanent_id,
            'format_profile' => 'pdfa_target',
        ]);
    }

    /**
     * @param  array{
     *   structure_id?: int|null,
     *   transfer_id?: int|null,
     *   record_id?: int|null,
     *   records: list<ArchiveRecord>,
     *   notes?: string|null,
     *   immutable?: bool,
     *   format_profile?: string|null,
     * }  $options
     */
    private function buildPackage(User $actor, ArchivePackageType $type, array $options): ArchivePackage
    {
        $records = $options['records'] ?? [];
        if ($records === []) {
            throw new InvalidArgumentException('Aucune archive à empaqueter.');
        }

        $disk = Storage::disk('archives');
        if (! $disk->exists('packages')) {
            $disk->makeDirectory('packages');
        }

        $reference = strtoupper($type->value).'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $relativeZip = 'packages/'.strtolower($type->value).'/'.$reference.'.zip';

        if (config('archives.object_lock') && $disk->exists($relativeZip)) {
            throw new InvalidArgumentException('Object Lock : chemin paquet déjà occupé, écriture refusée.');
        }

        $absoluteZip = $disk->path($relativeZip);
        $dir = dirname($absoluteZip);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($absoluteZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('Impossible de créer le paquet ZIP.');
        }

        $manifestItems = [];
        $packageItems = [];

        foreach ($records as $index => $record) {
            $version = $record->documentVersion
                ?: ($record->document?->officialVersion ?: $record->document?->latestVersion);

            $entryName = sprintf(
                'content/%s/%s',
                $record->permanent_id,
                $version?->original_name ?: 'document.bin'
            );

            $checksum = $version?->checksum;
            $size = (int) ($version?->size ?? 0);
            $mime = $version?->mime_type;

            if ($version && $version->disk && $version->path && Storage::disk($version->disk)->exists($version->path)) {
                $bytes = Storage::disk($version->disk)->get($version->path);
                $zip->addFromString($entryName, $bytes);
                $checksum = $checksum ?: hash('sha256', $bytes);
                $size = strlen($bytes);
            } else {
                $stub = json_encode([
                    'warning' => 'Fichier source indisponible au moment de l’empaquetage',
                    'record' => $record->permanent_id,
                    'document_id' => $record->document_id,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $zip->addFromString($entryName.'.missing.json', $stub ?: '{}');
                $checksum = hash('sha256', (string) $stub);
                $size = strlen((string) $stub);
                $mime = 'application/json';
            }

            $meta = [
                'permanent_id' => $record->permanent_id,
                'reference' => $record->reference,
                'title' => $record->title,
                'status' => $record->status?->value ?? $record->status,
                'confidentiality' => $record->confidentiality,
                'structure_id' => $record->structure_id,
                'checksum' => $checksum,
                'document_id' => $record->document_id,
                'document_version_id' => $version?->id,
            ];
            $zip->addFromString(
                'metadata/'.$record->permanent_id.'.json',
                (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $manifestItems[] = [
                'permanent_id' => $record->permanent_id,
                'path' => $entryName,
                'checksum_sha256' => $checksum,
                'size' => $size,
            ];

            $packageItems[] = [
                'record' => $record,
                'version' => $version,
                'relative_path' => $entryName,
                'checksum' => $checksum,
                'size_bytes' => $size,
                'mime_type' => $mime,
            ];
        }

        $manifest = [
            'package_type' => $type->value,
            'reference' => $reference,
            'created_at' => now()->toIso8601String(),
            'created_by' => $actor->only(['id', 'name', 'email']),
            'oais' => [
                'sip' => $type === ArchivePackageType::Sip,
                'aip' => $type === ArchivePackageType::Aip,
                'dip' => $type === ArchivePackageType::Dip,
            ],
            'disclaimer' => 'Paquet archivistique applicatif. Non certifié ISO 14721.',
            'items' => $manifestItems,
            'format_profile' => $options['format_profile'] ?? 'original',
        ];

        $manifestJson = (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->addFromString('manifest.json', $manifestJson);
        $zip->addFromString('README.txt', "Paquet {$type->value} E-Trésor SAE\nRéférence: {$reference}\nRéversible hors application via manifest.json + content/\n");
        $zip->close();

        $packageChecksum = is_file($absoluteZip) ? hash_file('sha256', $absoluteZip) : null;
        $manifestChecksum = hash('sha256', $manifestJson);
        $sizeBytes = is_file($absoluteZip) ? filesize($absoluteZip) : 0;

        $package = ArchivePackage::query()->create([
            'reference' => $reference,
            'package_type' => $type,
            'status' => 'sealed',
            'structure_id' => $options['structure_id'] ?? null,
            'transfer_id' => $options['transfer_id'] ?? null,
            'record_id' => $options['record_id'] ?? ($records[0]->id ?? null),
            'created_by' => $actor->id,
            'disk' => 'archives',
            'path' => $relativeZip,
            'manifest_checksum' => $manifestChecksum,
            'package_checksum' => $packageChecksum,
            'size_bytes' => $sizeBytes ?: 0,
            'format_profile' => $options['format_profile'] ?? 'original',
            'is_immutable' => (bool) ($options['immutable'] ?? ($type === ArchivePackageType::Aip)),
            'sealed_at' => now(),
            'manifest' => $manifest,
            'notes' => $options['notes'] ?? null,
        ]);

        foreach ($packageItems as $item) {
            ArchivePackageItem::query()->create([
                'package_id' => $package->id,
                'record_id' => $item['record']->id,
                'document_id' => $item['record']->document_id,
                'document_version_id' => $item['version']?->id,
                'relative_path' => $item['relative_path'],
                'checksum' => $item['checksum'],
                'size_bytes' => $item['size_bytes'],
                'mime_type' => $item['mime_type'],
            ]);

            $item['record']->preservation_status = 'packaged';
            $item['record']->save();
        }

        ArchivePreservationEvent::query()->create([
            'record_id' => $package->record_id,
            'package_id' => $package->id,
            'event_type' => 'packaged_'.$type->value,
            'actor_id' => $actor->id,
            'payload' => [
                'reference' => $package->reference,
                'checksum' => $package->package_checksum,
            ],
            'occurred_at' => now(),
        ]);

        $this->audit->log('package.created', $package, $actor, [
            'type' => $type->value,
            'reference' => $package->reference,
        ]);

        return $package->fresh(['items', 'record', 'structure']);
    }

    public function replicate(User $actor, ArchivePackage $package): ArchivePackage
    {
        if (! $package->path || ! Storage::disk($package->disk)->exists($package->path)) {
            throw new InvalidArgumentException('Paquet introuvable sur le disque de préservation.');
        }

        $contents = Storage::disk($package->disk)->get($package->path);
        $replicaPath = 'replicas/'.$package->reference.'.zip';
        $replica = Storage::disk('archives_replica');

        if (config('archives.object_lock') && $replica->exists($replicaPath)) {
            throw new InvalidArgumentException('Object Lock : réplique déjà présente, écrasement refusé.');
        }

        $replica->put($replicaPath, $contents);

        $package->fill([
            'status' => 'replicated',
            'replicated_at' => now(),
            'replica_disk' => 'archives_replica',
            'replica_path' => $replicaPath,
        ])->save();

        ArchivePreservationEvent::query()->create([
            'record_id' => $package->record_id,
            'package_id' => $package->id,
            'event_type' => 'replicated',
            'actor_id' => $actor->id,
            'payload' => [
                'replica_disk' => 'archives_replica',
                'replica_path' => $replicaPath,
            ],
            'occurred_at' => now(),
        ]);

        $this->audit->log('package.replicated', $package, $actor);

        return $package->fresh();
    }
}
