<?php

namespace App\Services;

use App\Enums\DocumentAccessAbility;
use App\Enums\DocumentArchiveStatus;
use App\Enums\DocumentAttachmentKind;
use App\Enums\DocumentLinkRelation;
use App\Enums\DocumentOrigin;
use App\Enums\DocumentStatus;
use App\Enums\ExpectedAction;
use App\Jobs\IndexDocumentContentJob;
use App\Models\Document;
use App\Models\DocumentAccessRule;
use App\Models\DocumentAttachment;
use App\Models\DocumentLink;
use App\Models\DocumentTag;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Socle documentaire GED : création, métadonnées, versions, classement, partage.
 * Le workflow parapheur reste dans DocumentWorkflowService.
 */
class DocumentService
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
        private readonly PrivateDocumentStorage $storage,
        private readonly AuditLogger $audit,
        private readonly DocumentAccessService $access,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile|array{file: UploadedFile, kind?: string}>  $attachments
     */
    public function create(User $author, array $data, ?UploadedFile $mainFile = null, array $attachments = []): Document
    {
        $data['origin'] = $data['origin'] ?? DocumentOrigin::Ged->value;

        $document = $this->workflow->createDraft($author, $data, $mainFile, $attachments);

        if (! empty($data['tags']) && is_array($data['tags'])) {
            $this->syncTags($document, $data['tags']);
        }

        IndexDocumentContentJob::dispatch($document->id);

        return $document->fresh([
            'type', 'category', 'structure', 'ownerStructure', 'classificationNode',
            'author', 'latestVersion', 'attachments', 'tags',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMetadata(Document $document, User $actor, array $data): Document
    {
        if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)
            && empty($data['force_archive_metadata'])) {
            throw new InvalidArgumentException('Document figé : métadonnées non modifiables.');
        }

        $fillable = [
            'object', 'title', 'description', 'summary', 'document_type_id', 'category_id',
            'structure_id', 'owner_structure_id', 'classification_node_id', 'priority',
            'confidentiality', 'expected_action', 'document_date', 'due_date', 'keywords',
            'language', 'source', 'dossier_number', 'reference',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $document->{$field} = $data[$field];
            }
        }

        $document->save();

        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $this->syncTags($document, $data['tags']);
        }

        $this->audit->log('document.metadata_updated', $document, ['actor_id' => $actor->id]);

        return $document->fresh([
            'type', 'category', 'structure', 'classificationNode', 'tags', 'author', 'latestVersion',
        ]);
    }

    public function assignClassification(Document $document, User $actor, ?int $nodeId): Document
    {
        $document->classification_node_id = $nodeId;
        $document->save();

        $this->audit->log('document.classified_ged', $document, [
            'actor_id' => $actor->id,
            'classification_node_id' => $nodeId,
        ]);

        return $document->fresh(['classificationNode']);
    }

    public function addVersion(Document $document, User $user, UploadedFile $file, ?string $note = null): DocumentVersion
    {
        if ($document->archive_status === DocumentArchiveStatus::Gele || $document->isOnLegalHold()) {
            throw new InvalidArgumentException('Document gelé : nouvelle version impossible.');
        }

        $version = $this->workflow->addVersion($document, $user, $file, $note);
        IndexDocumentContentJob::dispatch($document->id);

        return $version;
    }

    public function addAttachment(
        Document $document,
        User $user,
        UploadedFile $file,
        string $kind = 'piece_jointe',
    ): DocumentAttachment {
        $kindEnum = DocumentAttachmentKind::tryFrom($kind) ?? DocumentAttachmentKind::PieceJointe;

        return $this->workflow->addAttachment($document, $user, $file, $kindEnum->value);
    }

    /**
     * @param  list<string>  $tagNames
     */
    public function syncTags(Document $document, array $tagNames): void
    {
        $ids = [];
        foreach ($tagNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $slug = Str::slug($name);
            $tag = DocumentTag::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => ltrim($name, '#')],
            );
            $ids[] = $tag->id;
        }

        $previous = $document->tags()->pluck('document_tags.id')->all();
        $document->tags()->sync($ids);

        // Recalcul usage_count simple
        $affected = array_unique(array_merge($previous, $ids));
        foreach ($affected as $tagId) {
            DocumentTag::query()->whereKey($tagId)->update([
                'usage_count' => DB::table('document_tag_document')->where('document_tag_id', $tagId)->count(),
            ]);
        }
    }

    public function linkDocuments(
        Document $source,
        Document $target,
        User $actor,
        string $relationType = 'related_to',
        ?string $note = null,
    ): DocumentLink {
        if ((int) $source->id === (int) $target->id) {
            throw new InvalidArgumentException('Impossible de lier un document à lui-même.');
        }

        $relation = DocumentLinkRelation::tryFrom($relationType) ?? DocumentLinkRelation::RelatedTo;

        $link = DocumentLink::query()->updateOrCreate(
            [
                'source_document_id' => $source->id,
                'target_document_id' => $target->id,
                'relation_type' => $relation->value,
            ],
            [
                'created_by' => $actor->id,
                'note' => $note,
            ]
        );

        $this->audit->log('document.linked', $source, [
            'target_id' => $target->id,
            'relation' => $relation->value,
            'actor_id' => $actor->id,
        ]);

        return $link->load(['source', 'target', 'creator']);
    }

    public function unlinkDocuments(DocumentLink $link, User $actor): void
    {
        $sourceId = $link->source_document_id;
        $link->delete();
        $this->audit->log('document.unlinked', Document::query()->find($sourceId) ?? new Document, [
            'link_id' => $link->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function share(
        Document $document,
        User $actor,
        DocumentAccessAbility $ability,
        ?int $userId = null,
        ?int $structureId = null,
        ?string $roleName = null,
        ?\DateTimeInterface $expiresAt = null,
    ): DocumentAccessRule {
        if (! $userId && ! $structureId && ! $roleName) {
            throw new InvalidArgumentException('Indiquer un utilisateur, une structure ou un rôle.');
        }

        $confidentiality = $document->confidentiality?->value ?? (string) $document->confidentiality;
        if (in_array($confidentiality, ['confidentiel', 'tres_confidentiel'], true)
            && ! $actor->can('admin.access')
            && ! $actor->can('dashboard.dg')) {
            throw new InvalidArgumentException('Partage restreint pour ce niveau de confidentialité.');
        }

        $rule = DocumentAccessRule::query()->create([
            'document_id' => $document->id,
            'user_id' => $userId,
            'structure_id' => $structureId,
            'role_name' => $roleName,
            'ability' => $ability->value,
            'granted_by' => $actor->id,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->log('document.shared', $document, [
            'ability' => $ability->value,
            'user_id' => $userId,
            'structure_id' => $structureId,
            'role_name' => $roleName,
            'actor_id' => $actor->id,
        ]);

        return $rule->load(['user', 'structure', 'granter']);
    }

    public function revokeShare(DocumentAccessRule $rule, User $actor): void
    {
        $document = $rule->document;
        $rule->delete();
        $this->audit->log('document.share_revoked', $document, [
            'rule_id' => $rule->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function archiveGed(Document $document, User $actor, ?string $comment = null): Document
    {
        $document->archive_status = DocumentArchiveStatus::Archive;
        $document->archived_at = $document->archived_at ?? now();
        if ($document->status !== DocumentStatus::Archive) {
            // Archivage GED sans forcément passer le state machine parapheur
            try {
                return $this->workflow->archive($document, $actor, $comment);
            } catch (\Throwable) {
                $document->status = DocumentStatus::Archive;
                $document->save();
            }
        } else {
            $document->save();
        }

        $this->audit->log('document.archived_ged', $document, ['actor_id' => $actor->id]);

        return $document->fresh();
    }

    public function softDelete(Document $document, User $actor): void
    {
        if ($document->isOnLegalHold()) {
            throw new InvalidArgumentException('Document gelé (legal hold) : suppression impossible.');
        }

        if (in_array($document->status, [DocumentStatus::Valide, DocumentStatus::Traite, DocumentStatus::Archive], true)) {
            throw new InvalidArgumentException('Un document validé ou archivé ne peut pas être supprimé comme un brouillon.');
        }

        if ($document->archive_status === DocumentArchiveStatus::Gele) {
            throw new InvalidArgumentException('Document gelé : suppression impossible.');
        }

        if ($document->versions()->where('is_official', true)->exists()) {
            throw new InvalidArgumentException('Une version officielle existe : suppression interdite.');
        }

        $document->delete();
        $this->audit->log('document.soft_deleted', $document, ['actor_id' => $actor->id]);
    }

    public function restore(Document $document, User $actor): Document
    {
        $document->restore();
        $this->audit->log('document.restored', $document, ['actor_id' => $actor->id]);

        return $document;
    }

    public function generateDossierNumber(User $author): string
    {
        return sprintf('DOS-%s-%06d', now()->format('Y'), random_int(1, 999999));
    }

    /**
     * Payload fiche GED enrichi.
     *
     * @return array<string, mixed>
     */
    public function detailPayload(Document $document, User $user): array
    {
        $document->loadMissing([
            'type', 'category', 'structure', 'ownerStructure', 'classificationNode',
            'author', 'currentAssignee', 'latestVersion', 'officialVersion', 'versions.uploader',
            'attachments.uploader', 'tags', 'accessRules.user', 'accessRules.structure',
            'outgoingLinks.target.type', 'incomingLinks.source.type',
            'comments.user', 'workflowInstance.workflow', 'visas.user', 'approvals.user',
            'actions.actor', 'meetings',
        ]);

        return [
            'id' => $document->id,
            'uuid' => $document->uuid,
            'reference' => $document->reference,
            'dossier_number' => $document->dossier_number,
            'object' => $document->object,
            'title' => $document->displayTitle(),
            'description' => $document->description,
            'summary' => $document->summary,
            'status' => $document->status?->value ?? $document->status,
            'priority' => $document->priority?->value ?? $document->priority,
            'confidentiality' => $document->confidentiality?->value ?? $document->confidentiality,
            'expected_action' => $document->expected_action?->value ?? $document->expected_action,
            'origin' => $document->origin?->value ?? $document->origin,
            'archive_status' => $document->archive_status?->value ?? $document->archive_status,
            'language' => $document->language,
            'source' => $document->source,
            'document_date' => optional($document->document_date)?->toDateString(),
            'due_date' => optional($document->due_date)?->toDateString(),
            'submitted_at' => optional($document->submitted_at)?->toIso8601String(),
            'archived_at' => optional($document->archived_at)?->toIso8601String(),
            'current_version' => $document->current_version,
            'keywords' => $document->keywords ?? [],
            'legal_hold_at' => optional($document->legal_hold_at)?->toIso8601String(),
            'legal_hold_reason' => $document->legal_hold_reason,
            'retention_until' => optional($document->retention_until)?->toDateString(),
            'retention_years' => $document->retention_years,
            'text_extraction_status' => $document->text_extraction_status,
            'indexed_at' => optional($document->indexed_at)?->toIso8601String(),
            'antivirus_status' => $document->antivirus_status,
            'is_favorite' => app(DocumentEngagementService::class)->isFavorite($user, $document),
            'type' => $document->type,
            'category' => $document->category,
            'structure' => $document->structure,
            'owner_structure' => $document->ownerStructure,
            'classification_node' => $document->classificationNode,
            'author' => $document->author,
            'current_assignee' => $document->currentAssignee,
            'latest_version' => $document->latestVersion,
            'official_version' => $document->officialVersion,
            'versions' => $document->versions,
            'attachments' => $document->attachments,
            'tags' => $document->tags,
            'access_rules' => $document->accessRules,
            'links' => [
                'outgoing' => $document->outgoingLinks,
                'incoming' => $document->incomingLinks,
            ],
            'comments' => $document->comments,
            'workflow_instance' => $document->workflowInstance,
            'visas' => $document->visas,
            'approvals' => $document->approvals,
            'actions' => $document->actions,
            'meetings' => $document->meetings,
            'permissions' => [
                'can_view' => $this->access->canAccess($user, $document),
                'can_download' => $this->access->canDownload($user, $document),
                'can_edit' => $this->access->canEdit($user, $document),
                'can_share' => $this->access->canShare($user, $document),
                'can_transmit' => $this->access->canTransmit($user, $document),
                'can_process' => $this->access->canProcess($user, $document),
            ],
        ];
    }
}
