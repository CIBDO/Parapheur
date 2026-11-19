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
     *   grouping?: string|null,
     *   source_channel?: string|null,
     *   piece_titles?: list<string>|null,
     *   piece_meta?: list<array<string, mixed>>|null,
     *   submit_after?: bool|null,
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

        $grouping = ($context['grouping'] ?? 'pieces') === 'dossier' ? 'dossier' : 'pieces';
        $channel = trim((string) ($context['source_channel'] ?? 'upload')) ?: 'upload';
        $pieceTitles = is_array($context['piece_titles'] ?? null) ? array_values($context['piece_titles']) : [];
        $pieceMeta = is_array($context['piece_meta'] ?? null) ? array_values($context['piece_meta']) : [];
        $submitAfter = (bool) ($context['submit_after'] ?? false);

        return DB::transaction(function () use ($actor, $files, $context, $structureId, $typeId, $grouping, $channel, $pieceTitles, $pieceMeta, $submitAfter) {
            $documentIds = [];
            $batchTitle = trim((string) ($context['title'] ?? '')) ?: 'Numérisation Archives';

            if ($grouping === 'dossier') {
                $main = $files[0];
                $attachments = array_slice($files, 1);
                $document = $this->ingestion->ingest($actor, [
                    'object' => $batchTitle,
                    'title' => $batchTitle,
                    'document_type_id' => $typeId,
                    'structure_id' => $structureId,
                    'owner_structure_id' => $structureId,
                    'origin' => DocumentOrigin::Ged->value,
                    'skip_institutional_enrichment' => true,
                    'source' => 'archives.digitize.'.$channel,
                ], $main, $attachments);
                $documentIds[] = $document->id;
            } else {
                foreach (array_values($files) as $index => $file) {
                    $fallback = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: ('Pièce '.($index + 1));
                    $name = trim((string) ($pieceTitles[$index] ?? '')) ?: $fallback;
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
                        'source' => 'archives.digitize.'.$channel,
                    ], $file);

                    $documentIds[] = $document->id;
                }
            }

            $notes = trim((string) ($context['notes'] ?? ''));
            $autoNote = 'Versement créé depuis le poste de numérisation Archives ('.$channel.', '.$grouping.').';
            $notes = $notes !== '' ? $notes."\n".$autoNote : $autoNote;

            $classification = array_filter([
                'classification_node_id' => $context['classification_node_id'] ?? null,
                'fonds_id' => $context['fonds_id'] ?? null,
                'series_id' => $context['series_id'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $transfer = $this->ingest->createTransfer($actor, [
                'structure_id' => $structureId,
                'producer_unit_id' => $context['producer_unit_id'] ?? null,
                'title' => $batchTitle,
                'typology' => $context['typology'] ?? 'numerisation',
                'notes' => $notes,
                'source_module' => 'archives',
                'source_ref' => 'digitize:'.$channel,
                'medium' => $context['medium'] ?? 'electronique',
                'document_ids' => $documentIds,
                'classification_node_id' => $context['classification_node_id'] ?? null,
                'fonds_id' => $context['fonds_id'] ?? null,
                'series_id' => $context['series_id'] ?? null,
            ]);

            // Enrichir métadonnées pièce (dates QC, checksum) après création
            $items = $transfer->items()->orderBy('sort_order')->get();
            foreach ($items as $index => $item) {
                $extra = is_array($pieceMeta[$index] ?? null) ? $pieceMeta[$index] : [];
                $meta = array_merge(is_array($item->metadata) ? $item->metadata : [], $classification, array_filter([
                    'date_start' => $extra['date_start'] ?? null,
                    'date_end' => $extra['date_end'] ?? null,
                    'qc_checksum' => $extra['qc_checksum'] ?? null,
                    'qc_status' => $extra['qc_status'] ?? null,
                    'qc_notes' => $extra['qc_notes'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''));
                $item->metadata = $meta;
                $item->save();
            }

            $this->audit->log('digitize.batch_created', $transfer, $actor, [
                'document_count' => count($documentIds),
                'file_count' => count($files),
                'grouping' => $grouping,
                'source_channel' => $channel,
                'document_ids' => $documentIds,
                'submit_after' => $submitAfter,
            ]);

            $transfer = $transfer->fresh(['items.document', 'structure', 'creator', 'producerUnit']);

            if ($submitAfter) {
                $transfer = $this->ingest->submit($transfer, $actor);
            }

            return $transfer->fresh(['items.document', 'structure', 'creator', 'producerUnit']);
        });
    }
}
