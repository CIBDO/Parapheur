<?php

namespace App\Services\Productions;

use App\Enums\DocumentOrigin;
use App\Enums\ExpectedAction;
use App\Enums\ProductionOccurrenceStatus;
use App\Models\DocumentType;
use App\Models\ProductionFile;
use App\Models\ProductionOccurrence;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\DocumentWorkflowService;
use App\Services\Tasks\TaskDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverableService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly ProductionOccurrenceService $occurrences,
    ) {}

    public function deposit(User $actor, ProductionOccurrence $occurrence, UploadedFile $file, ?string $description = null): ProductionFile
    {
        $this->occurrences->assertCanUpdate($actor, $occurrence);

        if (! $occurrence->status?->allowsDeposit()) {
            throw ValidationException::withMessages([
                'status' => 'Dépôt impossible pour le statut actuel.',
            ]);
        }

        $obligation = $occurrence->obligation;
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $allowed = array_map('strtolower', $obligation?->allowed_formats ?? ['pdf', 'xlsx', 'docx', 'csv']);

        if ($allowed !== [] && $extension !== '' && ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'Format non autorisé : '.$extension.'. Formats acceptés : '.implode(', ', $allowed),
            ]);
        }

        if ($obligation?->max_file_size_kb && $file->getSize() > $obligation->max_file_size_kb * 1024) {
            throw ValidationException::withMessages([
                'file' => 'Fichier trop volumineux.',
            ]);
        }

        $currentCount = $occurrence->files()->count();
        if ($obligation?->max_files && $currentCount >= $obligation->max_files) {
            throw ValidationException::withMessages([
                'file' => 'Nombre maximal de fichiers atteint.',
            ]);
        }

        return DB::transaction(function () use ($actor, $occurrence, $file, $description, $obligation) {
            $typeId = DocumentType::query()->value('id');
            $document = $this->documents->create($actor, [
                'object' => ($obligation?->label ?? 'Production').' — '.$file->getClientOriginalName(),
                'title' => $file->getClientOriginalName(),
                'document_type_id' => $typeId,
                'structure_id' => $occurrence->structure_id,
                'origin' => DocumentOrigin::Production->value,
                'confidentiality' => $obligation?->confidentiality?->value ?? 'normal',
            ], $file);

            $version = $document->latestVersion;
            $prodFile = ProductionFile::query()->create([
                'production_occurrence_id' => $occurrence->id,
                'document_id' => $document->id,
                'document_version_id' => $version?->id,
                'uploaded_by' => $actor->id,
                'logical_version' => 1,
                'description' => $description,
                'status' => 'deposited',
                'checksum' => $version?->checksum ?? hash_file('sha256', $file->getRealPath()),
            ]);

            if ($occurrence->status === ProductionOccurrenceStatus::AProduire
                || $occurrence->status === ProductionOccurrenceStatus::Rejetee
                || $occurrence->status === ProductionOccurrenceStatus::ACompleter) {
                $occurrence->status = ProductionOccurrenceStatus::Deposee;
            } elseif ($occurrence->status === ProductionOccurrenceStatus::EnPreparation) {
                $occurrence->status = ProductionOccurrenceStatus::Deposee;
            }

            $occurrence->deposited_at = $occurrence->deposited_at ?? now();
            $occurrence->save();

            $this->occurrences->recordEvent($occurrence, $actor, 'file_deposited', [
                'production_file_id' => $prodFile->id,
                'document_id' => $document->id,
            ]);

            return $prodFile->load(['document.latestVersion', 'uploader:id,name']);
        });
    }

    public function replaceVersion(User $actor, ProductionFile $prodFile, UploadedFile $file, ?string $description = null): ProductionFile
    {
        $occurrence = $prodFile->occurrence;
        $this->occurrences->assertCanUpdate($actor, $occurrence);

        if ($prodFile->isLocked()) {
            return $this->appendRectificativeVersion($actor, $prodFile, $file, $description);
        }

        return DB::transaction(function () use ($actor, $prodFile, $file, $description, $occurrence) {
            $document = $prodFile->document;
            $version = app(\App\Services\DocumentWorkflowService::class)->addVersion($document, $actor, $file, $description);

            $prodFile->document_version_id = $version->id;
            $prodFile->logical_version = (int) $prodFile->logical_version + 1;
            $prodFile->description = $description ?? $prodFile->description;
            $prodFile->checksum = $version->checksum ?? $prodFile->checksum;
            $prodFile->status = 'deposited';
            $prodFile->save();

            $this->occurrences->recordEvent($occurrence, $actor, 'file_version_replaced', [
                'production_file_id' => $prodFile->id,
                'document_version_id' => $version->id,
            ]);

            return $prodFile->fresh(['document.latestVersion', 'uploader:id,name']);
        });
    }

    public function remove(User $actor, ProductionFile $prodFile): void
    {
        $occurrence = $prodFile->occurrence;
        $this->occurrences->assertCanUpdate($actor, $occurrence);

        if ($prodFile->isLocked()) {
            throw ValidationException::withMessages([
                'file' => 'Impossible de supprimer un fichier déjà transmis.',
            ]);
        }

        if (in_array($occurrence->status, [
            ProductionOccurrenceStatus::Soumise,
            ProductionOccurrenceStatus::EnValidation,
            ProductionOccurrenceStatus::Validee,
            ProductionOccurrenceStatus::Transmise,
            ProductionOccurrenceStatus::Cloturee,
        ], true)) {
            throw ValidationException::withMessages([
                'file' => 'Suppression interdite après soumission.',
            ]);
        }

        $prodFile->delete();
        $this->occurrences->recordEvent($occurrence, $actor, 'file_removed', [
            'production_file_id' => $prodFile->id,
        ]);
    }

    /**
     * @param  array{title?: string, format?: string}  $data
     */
    public function createOffice(User $actor, ProductionOccurrence $occurrence, array $data = []): ProductionFile
    {
        $this->occurrences->assertCanUpdate($actor, $occurrence);
        $occurrence->loadMissing('task', 'obligation');

        if (! $occurrence->task) {
            throw ValidationException::withMessages([
                'task' => 'Aucune tâche producteur liée à cette occurrence.',
            ]);
        }

        if (! $occurrence->status?->allowsDeposit()) {
            throw ValidationException::withMessages([
                'status' => 'Création impossible pour le statut actuel.',
            ]);
        }

        $link = app(TaskDocumentService::class)->createOfficeDocument($actor, $occurrence->task, [
            'title' => $data['title'] ?? ($occurrence->obligation?->label ?? 'Livrable'),
            'format' => $data['format'] ?? 'docx',
            'document_type_id' => $data['document_type_id'] ?? null,
        ]);

        $document = $link->document;
        $document->origin = DocumentOrigin::Production;
        $document->save();

        $prodFile = ProductionFile::query()->create([
            'production_occurrence_id' => $occurrence->id,
            'document_id' => $document->id,
            'document_version_id' => $document->latestVersion?->id,
            'uploaded_by' => $actor->id,
            'logical_version' => 1,
            'description' => 'Document ONLYOFFICE',
            'status' => 'deposited',
            'checksum' => $document->latestVersion?->checksum,
        ]);

        if (in_array($occurrence->status, [
            ProductionOccurrenceStatus::AProduire,
            ProductionOccurrenceStatus::EnPreparation,
            ProductionOccurrenceStatus::Rejetee,
            ProductionOccurrenceStatus::ACompleter,
        ], true)) {
            $occurrence->status = ProductionOccurrenceStatus::Deposee;
        }
        $occurrence->deposited_at = $occurrence->deposited_at ?? now();
        $occurrence->save();

        $this->occurrences->recordEvent($occurrence, $actor, 'office_created', [
            'production_file_id' => $prodFile->id,
            'document_id' => $document->id,
        ]);

        return $prodFile->load(['document.latestVersion', 'uploader:id,name']);
    }

    public function classifyInGed(User $actor, ProductionFile $prodFile, ?int $classificationNodeId = null): ProductionFile
    {
        $occurrence = $prodFile->occurrence;
        $this->occurrences->assertCanUpdate($actor, $occurrence);

        $document = $prodFile->document;
        if (! $document) {
            throw ValidationException::withMessages(['file' => 'Document introuvable.']);
        }

        $document->origin = DocumentOrigin::Ged;
        if ($classificationNodeId) {
            $document->classification_node_id = $classificationNodeId;
        }
        $document->save();

        $this->occurrences->recordEvent($occurrence, $actor, 'classified_in_ged', [
            'production_file_id' => $prodFile->id,
            'document_id' => $document->id,
            'classification_node_id' => $classificationNodeId,
        ]);

        return $prodFile->fresh(['document.latestVersion', 'uploader:id,name']);
    }

    /**
     * @param  list<int>  $recipientIds
     */
    public function submitToParapheur(User $actor, ProductionOccurrence $occurrence, array $recipientIds = []): ProductionOccurrence
    {
        $this->occurrences->assertCanUpdate($actor, $occurrence);
        $occurrence->loadMissing('obligation.recipients', 'files.document');

        if (! $occurrence->obligation?->submit_to_parapheur) {
            throw ValidationException::withMessages([
                'parapheur' => 'Cette obligation n’est pas configurée pour une soumission au parapheur.',
            ]);
        }

        $ids = $recipientIds !== []
            ? $recipientIds
            : $occurrence->obligation->recipients->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();

        $recipients = User::query()->whereIn('id', $ids)->where('is_active', true)->get()->all();
        if ($recipients === []) {
            throw ValidationException::withMessages([
                'recipients' => 'Indiquez au moins un destinataire du parapheur.',
            ]);
        }

        $files = $occurrence->files;
        if ($files->isEmpty()) {
            throw ValidationException::withMessages(['files' => 'Aucun livrable à soumettre.']);
        }

        $workflow = app(DocumentWorkflowService::class);
        foreach ($files as $file) {
            if (! $file->document) {
                continue;
            }
            $workflow->submitAndTransmitToMany(
                $file->document,
                $actor,
                $recipients,
                ExpectedAction::Information,
                'Livrable de production '.$occurrence->reference,
                $occurrence->obligation->workflow_id,
            );
        }

        $this->occurrences->recordEvent($occurrence, $actor, 'submitted_to_parapheur', [
            'recipients' => count($recipients),
        ]);

        return $this->occurrences->find($actor, $occurrence);
    }

    private function appendRectificativeVersion(User $actor, ProductionFile $prodFile, UploadedFile $file, ?string $description): ProductionFile
    {
        $occurrence = $prodFile->occurrence;

        return DB::transaction(function () use ($actor, $prodFile, $file, $description, $occurrence) {
            $document = $prodFile->document;
            $version = app(DocumentWorkflowService::class)->addVersion($document, $actor, $file, $description);

            $created = ProductionFile::query()->create([
                'production_occurrence_id' => $occurrence->id,
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'uploaded_by' => $actor->id,
                'logical_version' => (int) $prodFile->logical_version + 1,
                'description' => $description ?? $prodFile->description,
                'status' => 'deposited',
                'checksum' => $version->checksum ?? $prodFile->checksum,
            ]);

            if ($occurrence->status === ProductionOccurrenceStatus::Transmise) {
                $occurrence->status = ProductionOccurrenceStatus::ACompleter;
                $occurrence->save();
            }

            $this->occurrences->recordEvent($occurrence, $actor, 'rectificative_version_added', [
                'production_file_id' => $created->id,
                'document_version_id' => $version->id,
            ]);

            return $created->fresh(['document.latestVersion', 'uploader:id,name']);
        });
    }
}
