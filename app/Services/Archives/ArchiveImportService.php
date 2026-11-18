<?php

namespace App\Services\Archives;

use App\Models\ArchiveBox;
use App\Models\ArchiveImport;
use App\Models\ArchiveRecord;
use App\Models\ArchiveStorageLocation;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchiveImportService
{
    public function __construct(
        private readonly ArchiveIngestService $ingest,
        private readonly ArchiveAuditService $audit,
        private readonly PhysicalArchiveService $physical,
    ) {}

    /**
     * @param  array<string, string>  $columnMapping
     */
    public function preview(
        User $actor,
        int $structureId,
        string $importType,
        UploadedFile $file,
        array $columnMapping = []
    ): ArchiveImport {
        if (! in_array($importType, ['records', 'physical'], true)) {
            throw new InvalidArgumentException('Type d’import invalide.');
        }

        $rows = $this->parseCsv($file);
        if ($rows === []) {
            throw new InvalidArgumentException('Fichier CSV vide ou illisible.');
        }

        $headers = array_keys($rows[0]);
        $mapping = $columnMapping !== [] ? $columnMapping : $this->defaultMapping($importType, $headers);

        $preview = [];
        $valid = 0;
        $duplicates = 0;
        $errors = 0;

        foreach ($rows as $index => $row) {
            $mapped = $this->applyMapping($row, $mapping);
            $check = $this->validateRow($importType, $mapped, $structureId);
            $preview[] = [
                'line' => $index + 2,
                'data' => $mapped,
                'status' => $check['status'],
                'message' => $check['message'],
            ];
            match ($check['status']) {
                'ok' => $valid++,
                'duplicate' => $duplicates++,
                default => $errors++,
            };
        }

        $import = ArchiveImport::query()->create([
            'reference' => 'IMP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'import_type' => $importType,
            'status' => 'preview',
            'structure_id' => $structureId,
            'created_by' => $actor->id,
            'original_filename' => $file->getClientOriginalName(),
            'row_count' => count($rows),
            'valid_count' => $valid,
            'duplicate_count' => $duplicates,
            'error_count' => $errors,
            'column_mapping' => $mapping,
            'preview_rows' => array_slice($preview, 0, 200),
            'report' => ['preview_truncated' => count($preview) > 200],
        ]);

        $this->audit->log('import.previewed', $import, $actor, [
            'rows' => count($rows),
            'valid' => $valid,
        ]);

        return $import;
    }

    public function commit(User $actor, ArchiveImport $import): ArchiveImport
    {
        if ($import->status !== 'preview') {
            throw new InvalidArgumentException('Seuls les imports en prévisualisation peuvent être confirmés.');
        }

        $rows = $import->preview_rows ?? [];
        $committed = 0;
        $failures = [];

        DB::transaction(function () use ($actor, $import, $rows, &$committed, &$failures) {
            foreach ($rows as $row) {
                if (($row['status'] ?? '') !== 'ok') {
                    continue;
                }
                try {
                    $this->commitRow($actor, $import, $row['data'] ?? []);
                    $committed++;
                } catch (\Throwable $e) {
                    $failures[] = [
                        'line' => $row['line'] ?? null,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $import->fill([
                'status' => $failures === [] ? 'committed' : 'failed',
                'committed_count' => $committed,
                'committed_at' => now(),
                'report' => array_merge($import->report ?? [], [
                    'failures' => $failures,
                    'committed' => $committed,
                ]),
            ])->save();
        });

        $this->audit->log('import.committed', $import, $actor, [
            'committed' => $committed,
            'failures' => count($failures),
        ]);

        return $import->fresh();
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $raw = file_get_contents($file->getRealPath());
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $delimiter = substr_count($raw, ';') >= substr_count($raw, ',') ? ';' : ',';
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false || $headers === [null] || $headers === []) {
            fclose($handle);

            return [];
        }

        $headers = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->toString(), $headers);
        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === []) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private function defaultMapping(string $type, array $headers): array
    {
        $aliases = $type === 'physical'
            ? [
                'box_code' => ['box_code', 'code_boite', 'boite', 'code'],
                'location_code' => ['location_code', 'emplacement', 'full_code', 'cote'],
                'title' => ['title', 'titre', 'libelle'],
                'permanent_id' => ['permanent_id', 'id_permanent', 'pid'],
            ]
            : [
                'title' => ['title', 'titre', 'object', 'objet'],
                'reference' => ['reference', 'ref', 'cote'],
                'document_id' => ['document_id', 'doc_id', 'id_document'],
                'permanent_id' => ['permanent_id', 'id_permanent', 'pid'],
                'medium' => ['medium', 'support'],
                'physical_reference' => ['physical_reference', 'cote_papier', 'ref_papier'],
            ];

        $mapping = [];
        foreach ($aliases as $field => $candidates) {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $headers, true)) {
                    $mapping[$field] = $candidate;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    private function applyMapping(array $row, array $mapping): array
    {
        $out = [];
        foreach ($mapping as $field => $column) {
            $out[$field] = $row[$column] ?? '';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $data
     * @return array{status: string, message: string}
     */
    private function validateRow(string $type, array $data, int $structureId): array
    {
        if ($type === 'physical') {
            if (($data['box_code'] ?? '') === '' && ($data['location_code'] ?? '') === '') {
                return ['status' => 'error', 'message' => 'Code boîte ou emplacement requis'];
            }
            if (($data['box_code'] ?? '') !== '' && ArchiveBox::query()->where('code', $data['box_code'])->exists()) {
                return ['status' => 'duplicate', 'message' => 'Boîte déjà connue'];
            }

            return ['status' => 'ok', 'message' => 'Prêt'];
        }

        if (($data['title'] ?? '') === '' && ($data['document_id'] ?? '') === '') {
            return ['status' => 'error', 'message' => 'Titre ou document_id requis'];
        }

        if (($data['permanent_id'] ?? '') !== ''
            && ArchiveRecord::query()->where('permanent_id', $data['permanent_id'])->exists()) {
            return ['status' => 'duplicate', 'message' => 'permanent_id déjà présent'];
        }

        if (($data['document_id'] ?? '') !== '') {
            $docId = (int) $data['document_id'];
            if (! Document::query()->whereKey($docId)->exists()) {
                return ['status' => 'error', 'message' => 'document_id inconnu'];
            }
            if (ArchiveRecord::query()->where('document_id', $docId)->where('structure_id', $structureId)->exists()) {
                return ['status' => 'duplicate', 'message' => 'Document déjà archivé pour cette structure'];
            }
        }

        return ['status' => 'ok', 'message' => 'Prêt'];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function commitRow(User $actor, ArchiveImport $import, array $data): void
    {
        if ($import->import_type === 'physical') {
            $locationId = null;
            if (($data['location_code'] ?? '') !== '') {
                $location = ArchiveStorageLocation::query()
                    ->where('structure_id', $import->structure_id)
                    ->where(function ($q) use ($data) {
                        $q->where('full_code', $data['location_code'])
                            ->orWhere('code', $data['location_code']);
                    })
                    ->first();
                $locationId = $location?->id;
            }

            $this->physical->createBox($actor, [
                'structure_id' => $import->structure_id,
                'code' => $data['box_code'] ?: ('BOX-IMP-'.Str::upper(Str::random(6))),
                'label' => $data['title'] ?: 'Boîte importée',
                'location_id' => $locationId,
            ]);

            return;
        }

        if (($data['document_id'] ?? '') !== '') {
            $this->ingest->createTransfer($actor, [
                'structure_id' => $import->structure_id,
                'title' => $data['title'] ?: 'Import '.$import->reference,
                'document_ids' => [(int) $data['document_id']],
                'source_module' => 'import',
                'source_ref' => $import->reference,
                'medium' => in_array($data['medium'] ?? '', ['electronique', 'physique', 'hybride'], true)
                    ? $data['medium']
                    : 'electronique',
                'notes' => 'Import CSV '.$import->reference,
            ]);

            return;
        }

        // Record métadonnées seules : nécessite un document placeholder — refusé sans document_id.
        throw new InvalidArgumentException('Import dossiers numériques : document_id obligatoire (pas de document inventé).');
    }
}
