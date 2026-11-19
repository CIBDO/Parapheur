<?php

namespace App\Services\Archives;

use App\Enums\DocumentOrigin;
use App\Models\ArchiveTransfer;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentIngestionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ArchiveDigitizeService
{
    public function __construct(
        private readonly DocumentIngestionService $ingestion,
        private readonly ArchiveIngestService $ingest,
        private readonly ArchiveAuditService $audit,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  array{
     *   structure_id: int,
     *   producer_unit_id?: int|null,
     *   title?: string|null,
     *   typology?: string|null,
     *   notes?: string|null,
     *   medium?: string|null,
     *   document_type_id?: int|null,
     *   classification_node_id?: int|null,
     *   fonds_id?: int|null,
     *   series_id?: int|null,
     * }  $context
     */
    public function batch(User $actor, array $files, array $context): ArchiveTransfer
    {
        if ($files === []) {
            throw new InvalidArgumentException('Au moins un fichier est requis.');
        }

        $structureId = (int) ($context['structure_id'] ?? 0);
        if ($structureId <= 0) {
            throw new InvalidArgumentException('La structure est obligatoire.');
        }

        $typeId = (int) ($context['document_type_id'] ?? 0);
        if ($typeId <= 0) {
            $typeId = (int) DocumentType::query()->value('id');
        }
        if ($typeId <= 0) {
            throw new InvalidArgumentException('Aucun type de document disponible.');
        }

        return DB::transaction(function () use ($actor, $files, $context, $structureId, $typeId) {
            $documentIds = [];
            $batchTitle = trim((string) ($context['title'] ?? '')) ?: 'Numérisation Archives';

            foreach (array_values($files) as $index => $file) {
                $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: ('Pièce '.($index + 1));
                $object = count($files) > 1
                    ? $batchTitle.' — '.$name
                    : $batchTitle;

                $document = $this->ingestion->ingest($actor, [
                    'object' => $object,
                    'title' => $name,
                    'document_type_id' => $typeId,
                    'structure_id' => $structureId,
                    'owner_structure_id' => $structureId,
                    'origin' => DocumentOrigin::Ged->value,
                    'skip_institutional_enrichment' => true,
                    'source' => 'archives.digitize',
                ], $file);

                $documentIds[] = $document->id;
            }

            $transfer = $this->ingest->createTransfer($actor, [
                'structure_id' => $structureId,
                'producer_unit_id' => $context['producer_unit_id'] ?? null,
                'title' => $batchTitle,
                'typology' => $context['typology'] ?? 'numerisation',
                'notes' => $context['notes'] ?? 'Versement créé depuis le poste de numérisation Archives.',
                'source_module' => 'archives',
                'source_ref' => 'digitize',
                'medium' => $context['medium'] ?? 'electronique',
                'document_ids' => $documentIds,
                'classification_node_id' => $context['classification_node_id'] ?? null,
                'fonds_id' => $context['fonds_id'] ?? null,
                'series_id' => $context['series_id'] ?? null,
            ]);

            $this->audit->log('digitize.batch_created', $transfer, $actor, [
                'document_count' => count($documentIds),
                'document_ids' => $documentIds,
            ]);

            return $transfer->fresh(['items.document', 'structure', 'creator']);
        });
    }
}
