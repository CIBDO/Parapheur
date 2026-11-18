<?php

namespace App\Services\Archives;

use App\Models\ArchiveTransfer;
use App\Models\Document;
use App\Models\User;
use InvalidArgumentException;

class ArchiveModuleBridgeService
{
    public function __construct(
        private readonly ArchiveIngestService $ingest,
        private readonly ArchiveAuditService $audit,
    ) {}

    /**
     * Propose un versement SAE brouillon depuis un document métier (action explicite).
     *
     * @param  array{
     *   source_module?: string|null,
     *   source_ref?: string|null,
     *   structure_id?: int|null,
     *   producer_unit_id?: int|null,
     *   title?: string|null,
     *   typology?: string|null,
     *   notes?: string|null,
     *   medium?: string|null,
     * }  $context
     */
    public function proposeFromDocument(User $actor, Document $document, array $context = []): ArchiveTransfer
    {
        $structureId = (int) ($context['structure_id'] ?? $document->structure_id ?? $document->owner_structure_id);
        if ($structureId <= 0) {
            throw new InvalidArgumentException('Impossible de déterminer la structure du versement.');
        }

        $module = $context['source_module'] ?? ($document->origin?->value ?? $document->origin ?? 'ged');
        $sourceRef = $context['source_ref'] ?? $document->reference;

        $notes = trim((string) ($context['notes'] ?? ''));
        $bridgeNote = 'Proposition SAE depuis module '.$module
            .($sourceRef ? ' (réf. '.$sourceRef.')' : '');
        $notes = $notes !== '' ? $notes."\n".$bridgeNote : $bridgeNote;

        $transfer = $this->ingest->createTransfer($actor, [
            'structure_id' => $structureId,
            'producer_unit_id' => $context['producer_unit_id'] ?? null,
            'title' => $context['title'] ?? ($document->title ?: $document->object ?: 'Proposition SAE'),
            'typology' => $context['typology'] ?? null,
            'notes' => $notes,
            'document_ids' => [$document->id],
            'source_module' => (string) $module,
            'source_ref' => $sourceRef ? (string) $sourceRef : null,
            'medium' => $context['medium'] ?? 'electronique',
        ]);

        $this->audit->log('transfer.proposed_from_module', $transfer, $actor, [
            'source_module' => $module,
            'source_ref' => $sourceRef,
            'document_id' => $document->id,
        ]);

        return $transfer;
    }
}
