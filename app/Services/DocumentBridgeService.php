<?php

namespace App\Services;

use App\Enums\DocumentLinkRelation;
use App\Enums\DocumentOrigin;
use App\Enums\ExpectedAction;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Instruction;
use App\Models\Meeting;
use App\Models\User;
use App\Models\WorkspaceDocumentLink;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pont entre espace personnel/collaboratif et GED / parapheur / contextes métier.
 */
class DocumentBridgeService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentWorkflowService $workflow,
        private readonly MeetingService $meetings,
        private readonly AppointmentService $appointments,
        private readonly DocumentAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Soumettre un document d’espace vers le catalogue GED (changement d’origine, même fichier).
     */
    public function submitToGed(Document $document, User $actor, ?int $classificationNodeId = null): Document
    {
        $this->assertWorkspaceBound($document);

        if ((int) $document->author_id !== (int) $actor->id && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Seul l’auteur peut soumettre ce document à la GED.');
        }

        return DB::transaction(function () use ($document, $actor, $classificationNodeId) {
            $document->origin = DocumentOrigin::Ged;
            if ($classificationNodeId) {
                $document->classification_node_id = $classificationNodeId;
            }
            $document->save();

            $this->pinCurrentVersion($document, $actor, 'Version figée à l’entrée GED');

            $this->audit->log('workspace.submitted_to_ged', $document, [
                'actor_id' => $actor->id,
                'classification_node_id' => $classificationNodeId,
            ]);

            return $document->fresh(['type', 'classificationNode', 'author', 'latestVersion', 'officialVersion']);
        });
    }

    /**
     * Soumettre au parapheur en figeant la version courante (Vn).
     * Les versions ultérieures (Vn+1) n’écrasent pas la version figée.
     *
     * @param  list<User>  $recipients
     * @return array{document: Document, pinned_version_id: int|null}
     */
    public function submitToParapheur(
        Document $document,
        User $actor,
        array $recipients,
        ExpectedAction $expectedAction = ExpectedAction::Visa,
        ?string $message = null,
        ?int $workflowId = null,
        ?string $object = null,
        ?string $priority = null,
        ?string $confidentiality = null,
        ?string $dueDate = null,
    ): array {
        $this->assertWorkspaceBound($document);
        abort_unless($this->access->canAccess($actor, $document), 403);

        if ($object) {
            $document->object = $object;
            $document->title = $object;
        }
        if ($priority) {
            $document->priority = $priority;
        }
        if ($confidentiality) {
            $document->confidentiality = $confidentiality;
        }
        if ($dueDate) {
            $document->due_date = $dueDate;
        }
        $document->expected_action = $expectedAction;
        $document->origin = DocumentOrigin::Parapheur;
        $document->save();

        $pinned = $this->pinCurrentVersion($document, $actor, 'Version figée pour circuit parapheur');

        $result = $this->workflow->submitAndTransmitToMany(
            $document->fresh(),
            $actor,
            $recipients,
            $expectedAction,
            $message,
            $workflowId,
        );

        $this->audit->log('workspace.submitted_to_parapheur', $result, [
            'actor_id' => $actor->id,
            'pinned_version_id' => $pinned?->id,
            'recipient_ids' => array_map(fn (User $u) => $u->id, $recipients),
            'workflow_id' => $workflowId,
        ]);

        return [
            'document' => $result->fresh(['latestVersion', 'officialVersion', 'author', 'type']),
            'pinned_version_id' => $pinned?->id,
        ];
    }

    /**
     * Copie de travail dérivée d’un document officiel / figé.
     */
    public function createWorkingCopy(Document $source, User $actor, ?int $workspaceId = null, ?int $folderId = null): Document
    {
        abort_unless($this->access->canAccess($actor, $source), 403);

        return DB::transaction(function () use ($source, $actor, $workspaceId, $folderId) {
            $latest = $source->latestVersion;
            $copy = $this->workflow->createDraft($actor, [
                'object' => 'Copie de travail — '.($source->title ?: $source->object),
                'title' => 'Copie de travail — '.($source->title ?: $source->object),
                'document_type_id' => $source->document_type_id,
                'structure_id' => $actor->structure_id ?? $source->structure_id,
                'confidentiality' => $source->confidentiality?->value ?? 'normal',
                'priority' => $source->priority?->value ?? 'normale',
                'origin' => DocumentOrigin::Personal->value,
                'description' => $source->description,
                'keywords' => array_values(array_unique(array_merge(
                    (array) ($source->keywords ?? []),
                    ['copie_travail']
                ))),
            ]);

            if ($latest) {
                $this->workflow->addVersionFromContents(
                    $copy,
                    $actor,
                    \Illuminate\Support\Facades\Storage::disk($latest->disk)->get($latest->path),
                    $latest->original_name,
                    $latest->mime_type,
                    'Copie initiale depuis V'.$latest->version_number,
                );
            }

            $this->documents->linkDocuments(
                $copy,
                $source,
                $actor,
                DocumentLinkRelation::DerivedFrom->value,
            );

            if ($workspaceId) {
                WorkspaceDocumentLink::query()->firstOrCreate(
                    [
                        'workspace_id' => $workspaceId,
                        'document_id' => $copy->id,
                    ],
                    [
                        'folder_id' => $folderId,
                        'added_by' => $actor->id,
                    ]
                );
            }

            $this->audit->log('workspace.working_copy_created', $copy, [
                'actor_id' => $actor->id,
                'source_document_id' => $source->id,
            ]);

            return $copy->fresh(['latestVersion', 'outgoingLinks', 'type']);
        });
    }

    public function attachToMeeting(Document $document, User $actor, Meeting $meeting, array $meta = []): mixed
    {
        abort_unless($this->access->canAccess($actor, $document), 403);

        $link = $this->meetings->attachExistingDocument($actor, $meeting, $document->id, $meta);

        $this->audit->log('workspace.attached_to_meeting', $document, [
            'actor_id' => $actor->id,
            'meeting_id' => $meeting->id,
        ]);

        return $link;
    }

    public function attachToAppointment(Document $document, User $actor, Appointment $appointment, array $meta = []): mixed
    {
        abort_unless($this->access->canAccess($actor, $document), 403);

        $link = $this->appointments->attachExistingDocument($actor, $appointment, $document->id, $meta);

        $this->audit->log('workspace.attached_to_appointment', $document, [
            'actor_id' => $actor->id,
            'appointment_id' => $appointment->id,
        ]);

        return $link;
    }

    /**
     * Associe le document à une instruction (preuve / pièce) sans duplication.
     */
    public function attachToInstruction(Document $document, User $actor, Instruction $instruction, bool $asPrimary = false): Instruction
    {
        abort_unless($this->access->canAccess($actor, $document), 403);

        if ((int) $instruction->issuer_id !== (int) $actor->id
            && (int) $instruction->assignee_id !== (int) $actor->id
            && ! $actor->can('instructions.manage')
            && ! $actor->can('admin.access')) {
            throw new InvalidArgumentException('Vous ne pouvez pas associer un document à cette instruction.');
        }

        return DB::transaction(function () use ($document, $actor, $instruction, $asPrimary) {
            if ($asPrimary || ! $instruction->document_id) {
                $instruction->document_id = $document->id;
                $instruction->save();
            } elseif ($instruction->document_id && (int) $instruction->document_id !== (int) $document->id) {
                $primary = Document::query()->find($instruction->document_id);
                if ($primary) {
                    $this->documents->linkDocuments(
                        $document,
                        $primary,
                        $actor,
                        DocumentLinkRelation::RelatedTo->value,
                    );
                }
            }

            $this->audit->log('workspace.attached_to_instruction', $document, [
                'actor_id' => $actor->id,
                'instruction_id' => $instruction->id,
                'as_primary' => $asPrimary,
            ]);

            return $instruction->fresh(['document']);
        });
    }

    public function detachFromWorkspaceAfterGedSubmit(Document $document, bool $keepLink = true): void
    {
        if ($keepLink) {
            return;
        }

        WorkspaceDocumentLink::query()
            ->where('document_id', $document->id)
            ->delete();
    }

    private function assertWorkspaceBound(Document $document): void
    {
        $origin = $document->origin instanceof DocumentOrigin
            ? $document->origin
            : DocumentOrigin::tryFrom((string) $document->origin);

        if (! $origin?->isWorkspaceBound()) {
            throw new InvalidArgumentException('Seuls les documents personnels ou d’espace sont concernés.');
        }
    }

    private function pinCurrentVersion(Document $document, User $actor, string $note): ?DocumentVersion
    {
        $version = $document->latestVersion;
        if (! $version) {
            return null;
        }

        // Ne pas dé-officialiser d’autres versions déjà figées (ex. validation).
        $version->is_official = true;
        $version->change_note = trim(($version->change_note ? $version->change_note.' — ' : '').$note);
        $version->save();

        return $version;
    }
}
