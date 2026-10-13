<?php

namespace App\Services;

use App\Enums\DocumentArchiveStatus;
use App\Enums\DocumentPublicationStatus;
use App\Enums\PublicationWorkflow;
use App\Models\Document;
use App\Models\DocumentPublicationApproval;
use App\Models\User;
use App\Notifications\InstitutionalDocumentNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentPublicationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DocumentAccessService $access,
    ) {}

    public function submit(Document $document, User $actor, ?string $comment = null): Document
    {
        $this->assertCanContribute($actor, $document);

        $status = $this->resolveStatus($document);
        if (! in_array($status, [
            DocumentPublicationStatus::Brouillon,
            DocumentPublicationStatus::Depose,
            DocumentPublicationStatus::ACorriger,
            DocumentPublicationStatus::Rejete,
        ], true)) {
            throw new InvalidArgumentException('Ce document ne peut pas être soumis dans son état actuel.');
        }

        $workflow = $this->resolveWorkflow($document);

        return DB::transaction(function () use ($document, $actor, $comment, $workflow) {
            if ($workflow === PublicationWorkflow::Simple && $this->canPublish($actor, $document)) {
                return $this->publish($document, $actor, $comment ?? 'Publication directe (circuit simple)');
            }

            $document->publication_status = DocumentPublicationStatus::EnVerification;
            $document->publication_workflow = $workflow;
            $document->save();

            $this->record($document, $actor, 'submit', $comment, 1);
            $this->notifyValidators($document, $actor);

            return $document->fresh();
        });
    }

    public function approve(Document $document, User $actor, ?string $comment = null): Document
    {
        $this->assertCanValidate($actor, $document);
        $status = $this->resolveStatus($document);
        if ($status !== DocumentPublicationStatus::EnVerification) {
            throw new InvalidArgumentException('Seul un document en vérification peut être validé.');
        }

        return DB::transaction(function () use ($document, $actor, $comment) {
            $workflow = $this->resolveWorkflow($document);
            $step = (int) ($document->publicationApprovals()->max('step') ?? 0) + 1;

            if ($workflow === PublicationWorkflow::Hierarchical && $step < 2) {
                $document->publication_status = DocumentPublicationStatus::EnVerification;
                $document->save();
                $this->record($document, $actor, 'approve', $comment, $step);
                $this->notifyDocumentOwner($document, $actor);

                return $document->fresh();
            }

            $document->publication_status = DocumentPublicationStatus::Valide;
            $document->save();
            $this->record($document, $actor, 'approve', $comment, $step);

            if ($this->canPublish($actor, $document)) {
                return $this->publish($document, $actor, $comment);
            }

            $this->notifyPublishers($document, $actor);

            return $document->fresh();
        });
    }

    public function reject(Document $document, User $actor, string $motif): Document
    {
        $this->assertCanValidate($actor, $document);
        if (trim($motif) === '') {
            throw new InvalidArgumentException('Un motif de rejet est obligatoire.');
        }

        $document->publication_status = DocumentPublicationStatus::Rejete;
        $document->save();
        $this->record($document, $actor, 'reject', $motif);
        $this->notifyAuthor($document, $actor, 'Document rejeté', $motif);

        return $document->fresh();
    }

    public function returnForCorrection(Document $document, User $actor, string $motif): Document
    {
        $this->assertCanValidate($actor, $document);
        if (trim($motif) === '') {
            throw new InvalidArgumentException('Un motif de retour est obligatoire.');
        }

        $document->publication_status = DocumentPublicationStatus::ACorriger;
        $document->save();
        $this->record($document, $actor, 'return', $motif);
        $this->notifyAuthor($document, $actor, 'Document à corriger', $motif);

        return $document->fresh();
    }

    public function publish(Document $document, User $actor, ?string $comment = null): Document
    {
        $this->assertCanPublish($actor, $document);

        $status = $this->resolveStatus($document);
        if (! in_array($status, [
            DocumentPublicationStatus::Valide,
            DocumentPublicationStatus::Depose,
            DocumentPublicationStatus::EnVerification,
            DocumentPublicationStatus::Brouillon,
        ], true) && $status !== DocumentPublicationStatus::Publie) {
            // Allow republish after minor metadata if already validated path
        }

        if ($status === DocumentPublicationStatus::Publie) {
            return $document;
        }

        if ($this->resolveWorkflow($document) !== PublicationWorkflow::Simple
            && ! in_array($status, [DocumentPublicationStatus::Valide, DocumentPublicationStatus::EnVerification], true)
            && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Le document doit être validé avant publication.');
        }

        $document->publication_status = DocumentPublicationStatus::Publie;
        $document->published_at = now();
        $document->save();

        $latest = $document->latestVersion;
        if ($latest && ! $latest->is_official) {
            $document->versions()->where('is_official', true)->update(['is_official' => false]);
            $latest->is_official = true;
            $latest->save();
        }

        $this->record($document, $actor, 'publish', $comment);
        $this->audit->log('document.published', $document, ['actor_id' => $actor->id]);

        return $document->fresh(['latestVersion', 'officialVersion']);
    }

    public function archivePublication(Document $document, User $actor, ?string $comment = null): Document
    {
        if (! $actor->can('ged.archive') && ! $actor->can('admin.access') && ! $this->access->canShare($actor, $document)) {
            abort(403);
        }

        $document->publication_status = DocumentPublicationStatus::Archive;
        $document->archive_status = DocumentArchiveStatus::Archive;
        $document->archived_at = $document->archived_at ?? now();
        $document->save();
        $this->record($document, $actor, 'archive', $comment);
        $this->audit->log('document.publication_archived', $document, ['actor_id' => $actor->id]);

        return $document->fresh();
    }

    public function canValidate(User $user, Document $document): bool
    {
        if ($user->can('admin.access') || $user->can('ged.validate')) {
            return true;
        }

        return (int) $document->document_owner_id === (int) $user->id
            || ((int) $document->owner_structure_id === (int) $user->structure_id && $user->can('documents.validate'));
    }

    public function canPublish(User $user, Document $document): bool
    {
        return $user->can('admin.access')
            || $user->can('ged.publish')
            || ((int) $document->document_owner_id === (int) $user->id && $user->can('ged.validate'));
    }

    private function assertCanContribute(User $user, Document $document): void
    {
        abort_unless(
            $user->can('ged.create') || $user->can('ged.upload') || $user->can('admin.access')
            || (int) $document->author_id === (int) $user->id,
            403
        );
    }

    private function assertCanValidate(User $user, Document $document): void
    {
        abort_unless($this->canValidate($user, $document), 403, 'Validation non autorisée.');
    }

    private function assertCanPublish(User $user, Document $document): void
    {
        abort_unless($this->canPublish($user, $document), 403, 'Publication non autorisée.');
    }

    private function resolveStatus(Document $document): DocumentPublicationStatus
    {
        if ($document->publication_status instanceof DocumentPublicationStatus) {
            return $document->publication_status;
        }

        return DocumentPublicationStatus::tryFrom((string) $document->publication_status)
            ?? DocumentPublicationStatus::Brouillon;
    }

    private function resolveWorkflow(Document $document): PublicationWorkflow
    {
        if ($document->publication_workflow instanceof PublicationWorkflow) {
            return $document->publication_workflow;
        }

        if ($document->publication_workflow) {
            return PublicationWorkflow::tryFrom((string) $document->publication_workflow)
                ?? PublicationWorkflow::Controlled;
        }

        $folderWorkflow = $document->folder?->default_publication_workflow;
        if ($folderWorkflow instanceof PublicationWorkflow) {
            return $folderWorkflow;
        }

        return PublicationWorkflow::Controlled;
    }

    private function record(Document $document, User $actor, string $action, ?string $comment = null, ?int $step = null): void
    {
        DocumentPublicationApproval::query()->create([
            'document_id' => $document->id,
            'step' => $step ?? ((int) ($document->publicationApprovals()->max('step') ?? 0) + 1),
            'actor_id' => $actor->id,
            'action' => $action,
            'comment' => $comment,
        ]);
    }

    private function notifyValidators(Document $document, User $actor): void
    {
        $targets = collect();
        if ($document->document_owner_id) {
            $owner = User::query()->find($document->document_owner_id);
            if ($owner) {
                $targets->push($owner);
            }
        }

        User::permission('ged.validate')->limit(20)->get()->each(fn (User $u) => $targets->push($u));

        foreach ($targets->unique('id') as $user) {
            if ((int) $user->id === (int) $actor->id) {
                continue;
            }
            $user->notify(new InstitutionalDocumentNotification(
                $document,
                'Document à valider',
                'Le document « '.$document->displayTitle().' » attend une validation institutionnelle.',
            ));
        }
    }

    private function notifyDocumentOwner(Document $document, User $actor): void
    {
        if (! $document->document_owner_id || (int) $document->document_owner_id === (int) $actor->id) {
            return;
        }
        $owner = User::query()->find($document->document_owner_id);
        $owner?->notify(new InstitutionalDocumentNotification(
            $document,
            'Validation structure effectuée',
            'Le document « '.$document->displayTitle().' » attend votre validation documentaire.',
        ));
    }

    private function notifyPublishers(Document $document, User $actor): void
    {
        User::permission('ged.publish')->limit(20)->get()->each(function (User $user) use ($document, $actor) {
            if ((int) $user->id === (int) $actor->id) {
                return;
            }
            $user->notify(new InstitutionalDocumentNotification(
                $document,
                'Document prêt à publier',
                'Le document « '.$document->displayTitle().' » a été validé.',
            ));
        });
    }

    private function notifyAuthor(Document $document, User $actor, string $title, string $body): void
    {
        if (! $document->author_id || (int) $document->author_id === (int) $actor->id) {
            return;
        }
        $author = User::query()->find($document->author_id);
        $author?->notify(new InstitutionalDocumentNotification(
            $document,
            $title,
            $body.' — « '.$document->displayTitle().' »',
        ));
    }
}
