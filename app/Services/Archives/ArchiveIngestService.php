<?php

namespace App\Services\Archives;

use App\Enums\ArchiveRecordStatus;
use App\Enums\ArchiveTransferStatus;
use App\Enums\DocumentArchiveStatus;
use App\Models\ArchiveRecord;
use App\Models\ArchiveTransfer;
use App\Models\ArchiveTransferItem;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArchiveIngestService
{
    public function __construct(
        private readonly ArchiveAuditService $audit,
    ) {}

    /**
     * @param  array{
     *   structure_id: int,
     *   producer_unit_id?: int|null,
     *   title?: string|null,
     *   typology?: string|null,
     *   notes?: string|null,
     *   document_ids?: list<int>,
     *   source_module?: string|null,
     *   source_ref?: string|null,
     *   medium?: string|null,
     *   classification_node_id?: int|null,
     *   fonds_id?: int|null,
     *   series_id?: int|null,
     * }  $data
     */
    public function createTransfer(User $actor, array $data): ArchiveTransfer
    {
        return DB::transaction(function () use ($actor, $data) {
            $transfer = ArchiveTransfer::query()->create([
                'reference' => $this->nextReference(),
                'status' => ArchiveTransferStatus::Brouillon,
                'structure_id' => (int) $data['structure_id'],
                'producer_unit_id' => $data['producer_unit_id'] ?? null,
                'created_by' => $actor->id,
                'title' => $data['title'] ?? null,
                'typology' => $data['typology'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source_module' => $data['source_module'] ?? null,
                'source_ref' => $data['source_ref'] ?? null,
                'medium' => in_array(($data['medium'] ?? 'electronique'), ['electronique', 'physique', 'hybride'], true)
                    ? ($data['medium'] ?? 'electronique')
                    : 'electronique',
            ]);

            $classification = array_filter([
                'classification_node_id' => $data['classification_node_id'] ?? null,
                'fonds_id' => $data['fonds_id'] ?? null,
                'series_id' => $data['series_id'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            foreach (array_values($data['document_ids'] ?? []) as $index => $documentId) {
                $this->attachDocument($transfer, (int) $documentId, $index, $classification);
            }

            $this->audit->log('transfer.created', $transfer, $actor, [
                'reference' => $transfer->reference,
            ]);

            return $transfer->fresh(['items.document', 'structure', 'creator']);
        });
    }

    public function submit(ArchiveTransfer $transfer, User $actor): ArchiveTransfer
    {
        $this->assertStatus($transfer, [ArchiveTransferStatus::Brouillon, ArchiveTransferStatus::Rejete]);

        if ($transfer->items()->count() === 0) {
            throw new InvalidArgumentException('Le versement doit contenir au moins une pièce.');
        }

        $transfer->fill([
            'status' => ArchiveTransferStatus::Soumis,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'rejection_reason' => null,
            'rejected_at' => null,
        ])->save();

        $this->audit->log('transfer.submitted', $transfer, $actor);

        return $transfer->fresh(['items.document', 'structure', 'creator']);
    }

    public function startControl(ArchiveTransfer $transfer, User $actor): ArchiveTransfer
    {
        $this->assertStatus($transfer, [ArchiveTransferStatus::Soumis]);

        $transfer->fill([
            'status' => ArchiveTransferStatus::EnControle,
            'controlled_by' => $actor->id,
            'controlled_at' => now(),
        ])->save();

        $this->audit->log('transfer.control_started', $transfer, $actor);

        return $transfer->fresh(['items.document', 'structure', 'creator']);
    }

    public function accept(ArchiveTransfer $transfer, User $actor): ArchiveTransfer
    {
        $this->assertStatus($transfer, [ArchiveTransferStatus::EnControle, ArchiveTransferStatus::Soumis]);

        $transfer->fill([
            'status' => ArchiveTransferStatus::Accepte,
            'accepted_by' => $actor->id,
            'accepted_at' => now(),
            'controlled_by' => $transfer->controlled_by ?: $actor->id,
            'controlled_at' => $transfer->controlled_at ?: now(),
        ])->save();

        $this->audit->log('transfer.accepted', $transfer, $actor);

        return $transfer->fresh(['items.document', 'structure', 'creator']);
    }

    public function reject(ArchiveTransfer $transfer, User $actor, string $reason): ArchiveTransfer
    {
        $this->assertStatus($transfer, [ArchiveTransferStatus::Soumis, ArchiveTransferStatus::EnControle]);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Le motif de rejet est obligatoire.');
        }

        $transfer->fill([
            'status' => ArchiveTransferStatus::Rejete,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'controlled_by' => $actor->id,
            'controlled_at' => now(),
        ])->save();

        $this->audit->log('transfer.rejected', $transfer, $actor, ['reason' => $reason]);

        return $transfer->fresh(['items.document', 'structure', 'creator']);
    }

    public function takeOver(ArchiveTransfer $transfer, User $actor): ArchiveTransfer
    {
        $this->assertStatus($transfer, [ArchiveTransferStatus::Accepte]);

        $result = DB::transaction(function () use ($transfer, $actor) {
            $transfer->fill([
                'status' => ArchiveTransferStatus::PrisEnCharge,
                'taken_over_at' => now(),
            ])->save();

            foreach ($transfer->items as $item) {
                $this->materializeRecord($transfer, $item, $actor);
            }

            $transfer->fill([
                'status' => ArchiveTransferStatus::Conserve,
                'conserved_at' => now(),
            ])->save();

            $this->audit->log('transfer.conserved', $transfer, $actor);

            return $transfer->fresh(['items.document', 'records', 'structure', 'creator']);
        });

        if (config('archives.auto_sip_on_accept')) {
            try {
                app(ArchivePackageService::class)->buildSipFromTransfer($actor, $result);
            } catch (\Throwable) {
                // SIP optionnel : ne bloque pas la conservation.
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $extraMetadata
     */
    private function attachDocument(
        ArchiveTransfer $transfer,
        int $documentId,
        int $sortOrder,
        array $extraMetadata = [],
    ): ArchiveTransferItem {
        $document = Document::query()->with(['officialVersion', 'latestVersion'])->findOrFail($documentId);
        $version = $document->officialVersion ?: $document->latestVersion;

        $document->archive_status = DocumentArchiveStatus::AVerser;
        $document->save();

        return ArchiveTransferItem::query()->create([
            'transfer_id' => $transfer->id,
            'document_id' => $document->id,
            'document_version_id' => $version?->id,
            'title' => $document->title ?: $document->object,
            'checksum' => $version?->checksum,
            'metadata' => array_merge([
                'reference' => $document->reference,
                'origin' => $document->origin?->value ?? $document->origin,
            ], $extraMetadata),
            'sort_order' => $sortOrder,
        ]);
    }

    private function materializeRecord(ArchiveTransfer $transfer, ArchiveTransferItem $item, User $actor): ArchiveRecord
    {
        $document = $item->document()->with(['officialVersion', 'latestVersion'])->firstOrFail();
        $version = $item->documentVersion
            ?: ($document->officialVersion ?: $document->latestVersion);

        $medium = (string) ($transfer->medium ?: 'electronique');
        if (! in_array($medium, ['electronique', 'physique', 'hybride'], true)) {
            $medium = 'electronique';
        }

        $meta = is_array($item->metadata) ? $item->metadata : [];

        $record = ArchiveRecord::query()->create([
            'permanent_id' => $this->nextPermanentId(),
            'reference' => $document->reference,
            'title' => $item->title ?: ($document->title ?: $document->object),
            'description' => $document->description,
            'status' => ArchiveRecordStatus::Conserve,
            'confidentiality' => $document->confidentiality?->value ?? (string) $document->confidentiality,
            'structure_id' => $transfer->structure_id,
            'producer_unit_id' => $transfer->producer_unit_id,
            'fonds_id' => isset($meta['fonds_id']) ? (int) $meta['fonds_id'] : null,
            'series_id' => isset($meta['series_id']) ? (int) $meta['series_id'] : null,
            'classification_node_id' => isset($meta['classification_node_id']) ? (int) $meta['classification_node_id'] : null,
            'transfer_id' => $transfer->id,
            'document_id' => $document->id,
            'document_version_id' => $version?->id,
            'checksum' => $item->checksum ?: $version?->checksum,
            'typology' => $transfer->typology,
            'medium' => $medium,
            'date_start' => $document->document_date,
            'date_end' => $document->document_date,
            'created_by' => $actor->id,
            'conserved_at' => now(),
        ]);

        $document->archive_status = DocumentArchiveStatus::Verse;
        $document->save();

        $this->audit->log('record.created', $record, $actor, [
            'permanent_id' => $record->permanent_id,
            'transfer_id' => $transfer->id,
        ]);

        return $record;
    }

    /**
     * @param  list<ArchiveTransferStatus>  $allowed
     */
    private function assertStatus(ArchiveTransfer $transfer, array $allowed): void
    {
        $status = $transfer->status instanceof ArchiveTransferStatus
            ? $transfer->status
            : ArchiveTransferStatus::from((string) $transfer->status);

        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException(
                'Transition interdite depuis le statut '.$status->value.'.'
            );
        }
    }

    private function nextReference(): string
    {
        return 'VR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function nextPermanentId(): string
    {
        return 'AR-'.now()->format('Y').'-'.Str::upper(Str::random(10));
    }
}
