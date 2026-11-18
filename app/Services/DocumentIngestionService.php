<?php

namespace App\Services;

use App\Enums\DocumentOrigin;
use App\Enums\DocumentPublicationStatus;
use App\Jobs\IndexDocumentContentJob;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Façade unique d’ingestion documentaire durable.
 *
 * Garantit origine, antivirus, checksum (via storage), indexation.
 * Statuts : rédaction → brouillon ; versement institutionnel → depose (via DocumentService/GED).
 */
class DocumentIngestionService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentWorkflowService $workflow,
        private readonly PrivateDocumentStorage $storage,
        private readonly AntivirusScanService $antivirus,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Création standard (pipeline DocumentService : antivirus + index + dépôt GED si origin=ged).
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile|array{file: UploadedFile, kind?: string}>  $attachments
     */
    public function ingest(User $actor, array $data, ?UploadedFile $mainFile = null, array $attachments = []): Document
    {
        return $this->documents->create($actor, $data, $mainFile, $attachments);
    }

    /**
     * Brouillon métier (réunions, livrables en préparation) — hors catalogue institutionnel.
     *
     * @param  array<string, mixed>  $data
     */
    public function ingestWorkingDraft(User $actor, array $data, ?UploadedFile $mainFile = null): Document
    {
        $data['origin'] = $data['origin'] ?? DocumentOrigin::Parapheur->value;
        $data['publication_status'] = $data['publication_status']
            ?? DocumentPublicationStatus::Brouillon->value;
        $data['skip_institutional_enrichment'] = true;

        $document = $this->workflow->createDraft($actor, $data, $mainFile);

        if ($mainFile) {
            $this->antivirus->scanDocument($document);
        }

        IndexDocumentContentJob::dispatch($document->id);

        $this->audit->log('document.ingested_draft', $document, [
            'actor_id' => $actor->id,
            'origin' => $document->origin?->value ?? $document->origin,
        ]);

        return $document->fresh([
            'type', 'structure', 'author', 'latestVersion', 'attachments',
        ]);
    }

    /**
     * Persiste un contenu généré (HTML, DOCX…) comme version principale + indexation.
     *
     * @param  array<string, mixed>  $data
     */
    public function ingestGeneratedContent(
        User $actor,
        array $data,
        string $binaryOrText,
        string $filename,
        string $mimeType,
        ?string $changeNote = null,
    ): Document {
        return DB::transaction(function () use ($actor, $data, $binaryOrText, $filename, $mimeType, $changeNote) {
            $data['publication_status'] = $data['publication_status']
                ?? DocumentPublicationStatus::Brouillon->value;
            $data['skip_institutional_enrichment'] = true;

            $document = $this->workflow->createDraft($actor, $data);

            $stored = $this->storage->storeContent(
                $binaryOrText,
                $filename,
                $mimeType,
                'documents/'.$document->uuid
            );

            $document->versions()->where('is_main', true)->update(['is_main' => false]);

            DocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => 1,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'checksum' => $stored['checksum'],
                'is_main' => true,
                'uploaded_by' => $actor->id,
                'change_note' => $changeNote ?? 'Génération automatique',
                'change_source' => 'system',
            ]);

            $document->current_version = 1;
            $document->save();

            $document = $document->fresh(['latestVersion']);
            $this->antivirus->scanDocument($document);
            IndexDocumentContentJob::dispatch($document->id);

            $this->audit->log('document.ingested_generated', $document, [
                'actor_id' => $actor->id,
                'origin' => $document->origin?->value ?? $document->origin,
                'mime_type' => $mimeType,
            ]);

            return $document->fresh([
                'type', 'structure', 'author', 'latestVersion',
            ]);
        });
    }
}
