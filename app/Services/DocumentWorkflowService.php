<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ExpectedAction;
use App\Enums\ParapheurFolder;
use App\Enums\WorkflowActionType;
use App\Models\Approval;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentTransmission;
use App\Models\DocumentVersion;
use App\Models\Instruction;
use App\Models\User;
use App\Models\Visa;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentWorkflowService
{
    public function __construct(
        private readonly DocumentStateMachine $stateMachine,
        private readonly PrivateDocumentStorage $storage,
        private readonly AuditLogger $audit,
        private readonly DelegationResolver $delegations,
        private readonly DocumentAccessService $access,
    ) {}

    public function createDraft(User $author, array $data, ?UploadedFile $mainFile = null, array $attachments = []): Document
    {
        return DB::transaction(function () use ($author, $data, $mainFile, $attachments) {
            $document = Document::query()->create([
                'uuid' => (string) Str::uuid(),
                'reference' => $data['reference'] ?? $this->generateReference($author),
                'dossier_number' => $data['dossier_number'] ?? null,
                'object' => $data['object'],
                'title' => $data['title'] ?? $data['object'],
                'description' => $data['description'] ?? null,
                'summary' => $data['summary'] ?? null,
                'document_type_id' => $data['document_type_id'],
                'category_id' => $data['category_id'] ?? null,
                'structure_id' => $data['structure_id'] ?? $author->structure_id,
                'owner_structure_id' => $data['owner_structure_id'] ?? ($data['structure_id'] ?? $author->structure_id),
                'classification_node_id' => $data['classification_node_id'] ?? null,
                'author_id' => $author->id,
                'status' => DocumentStatus::Brouillon,
                'priority' => $data['priority'] ?? 'normale',
                'confidentiality' => $data['confidentiality'] ?? 'normal',
                'expected_action' => $data['expected_action'] ?? ExpectedAction::Consultation->value,
                'document_date' => $data['document_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'keywords' => $data['keywords'] ?? [],
                'origin' => $data['origin'] ?? 'parapheur',
                'language' => $data['language'] ?? 'fr',
                'source' => $data['source'] ?? null,
                'archive_status' => $data['archive_status'] ?? 'actif',
                'current_version' => $mainFile ? 1 : 0,
            ]);

            if ($mainFile) {
                $this->storeMainVersion($document, $author, $mainFile, 'Version initiale');
            }

            foreach ($attachments as $attachment) {
                if ($attachment instanceof UploadedFile) {
                    $this->storeAttachment($document, $author, $attachment);

                    continue;
                }

                if (is_array($attachment) && ($attachment['file'] ?? null) instanceof UploadedFile) {
                    $this->storeAttachment(
                        $document,
                        $author,
                        $attachment['file'],
                        $attachment['kind'] ?? 'piece_jointe',
                    );
                }
            }

            $this->audit->log('document.created', $document);

            return $document->fresh(['type', 'structure', 'author', 'latestVersion', 'attachments']);
        });
    }

    public function submitAndTransmit(
        Document $document,
        User $from,
        ?User $to,
        ExpectedAction $expectedAction,
        ?string $message = null,
        ?int $workflowId = null,
    ): Document {
        return $this->submitAndTransmitToMany(
            $document,
            $from,
            $to ? [$to] : [],
            $expectedAction,
            $message,
            $workflowId,
        );
    }

    /**
     * Transmission libre vers un ou plusieurs destinataires (parallèle),
     * ou circuit prédéfini (un destinataire résolu par l’étape).
     *
     * @param  list<User>  $recipients
     */
    public function submitAndTransmitToMany(
        Document $document,
        User $from,
        array $recipients,
        ExpectedAction $expectedAction,
        ?string $message = null,
        ?int $workflowId = null,
    ): Document {
        return DB::transaction(function () use ($document, $from, $recipients, $expectedAction, $message, $workflowId) {
            if ($document->status === DocumentStatus::Brouillon) {
                $this->transition($document, DocumentStatus::Depose);
                $document->submitted_at = now();
                $document->save();
            }

            // Après retour : la retransmission marque le dossier comme corrigé.
            if ($document->status === DocumentStatus::ACorriger) {
                $this->transition($document, DocumentStatus::Corrige);
            }

            if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
                throw new InvalidArgumentException('Document figé : transmission impossible.');
            }

            $workflow = null;
            $step = null;
            $kind = 'libre';
            $targets = [];

            if ($workflowId) {
                $workflow = Workflow::query()->with('steps')->where('is_active', true)->findOrFail($workflowId);
                $steps = $workflow->steps->sortBy('step_order')->values();
                if ($steps->isEmpty()) {
                    throw new InvalidArgumentException('Le circuit sélectionné ne contient aucune étape.');
                }

                $to = null;
                foreach ($steps as $candidate) {
                    $assignee = $this->resolveStepAssignee($candidate, $document, $from);
                    if ((int) $assignee->id !== (int) $from->id) {
                        $step = $candidate;
                        $to = $assignee;
                        break;
                    }
                }

                if (! $step) {
                    $step = $steps->first();
                    $to = $this->resolveStepAssignee($step, $document, $from);
                }

                $kind = 'predefini';
                $expectedAction = $step->expected_action ?? $expectedAction;
                $targets = [$to];
            } else {
                $seen = [];
                foreach ($recipients as $user) {
                    if (! $user instanceof User) {
                        continue;
                    }
                    $id = (int) $user->id;
                    if ($id === (int) $from->id || isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $targets[] = $user;
                }
            }

            if ($targets === []) {
                throw new InvalidArgumentException('Au moins un destinataire de transmission est requis.');
            }

            foreach ($targets as $recipient) {
                if (! $this->access->canReceive($recipient, $document)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Le destinataire %s n’a pas le niveau d’habilitation requis pour ce document.',
                            $recipient->name ?: $recipient->email
                        )
                    );
                }
            }

            // Clôturer les transmissions en attente de l'expéditeur
            DocumentTransmission::query()
                ->where('document_id', $document->id)
                ->where('to_user_id', $from->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'done',
                    'acted_at' => now(),
                ]);

            $instance = WorkflowInstance::query()->create([
                'document_id' => $document->id,
                'workflow_id' => $workflow?->id,
                'kind' => $kind,
                'status' => 'en_cours',
                'current_step_order' => $step?->step_order ?? 1,
                'started_by' => $from->id,
            ]);

            $folder = $this->folderForAction($expectedAction);
            $primary = $targets[0];

            foreach ($targets as $recipient) {
                DocumentTransmission::query()->create([
                    'document_id' => $document->id,
                    'workflow_instance_id' => $instance->id,
                    'from_user_id' => $from->id,
                    'to_user_id' => $recipient->id,
                    'expected_action' => $expectedAction,
                    'folder' => $folder,
                    'status' => 'pending',
                    'message' => $message,
                ]);
            }

            $fromStatus = $document->status;

            if ($kind === 'predefini') {
                $this->transition($document, DocumentStatus::EnCircuit);
            } else {
                $this->transition($document, DocumentStatus::Transmis);
            }

            $targetStatus = match ($expectedAction) {
                ExpectedAction::Visa => DocumentStatus::AViser,
                ExpectedAction::Validation => DocumentStatus::AValider,
                ExpectedAction::Information => DocumentStatus::AConsulter,
                default => DocumentStatus::AConsulter,
            };
            $this->transition($document, $targetStatus);

            $document->expected_action = $expectedAction;
            $document->current_assignee_id = $primary->id;
            $document->save();

            $this->recordAction(
                $document,
                $from,
                WorkflowActionType::Transmettre,
                $fromStatus,
                $document->status,
                $message,
                $instance->id,
            );

            $this->audit->log('document.transmitted', $document, [
                'to_user_ids' => array_map(fn (User $u) => $u->id, $targets),
                'expected_action' => $expectedAction->value,
                'workflow_id' => $workflow?->id,
                'kind' => $kind,
            ]);

            foreach ($targets as $recipient) {
                $this->notifyUser(
                    $recipient,
                    $document,
                    'transmitted',
                    sprintf('%s vous a transmis un document pour %s.', $from->name, $expectedAction->label()),
                    $from->name,
                );
            }

            return $document->fresh(['type', 'structure', 'author', 'currentAssignee', 'latestVersion', 'workflowInstance.workflow.steps']);
        });
    }

    public function advancePredefinedWorkflow(Document $document, User $actor, ?string $message = null): ?Document
    {
        $instance = $document->workflowInstance;
        if (! $instance || $instance->kind !== 'predefini' || ! $instance->workflow_id) {
            return null;
        }

        $workflow = Workflow::query()->with('steps')->find($instance->workflow_id);
        if (! $workflow) {
            return null;
        }

        $steps = $workflow->steps->sortBy('step_order')->values();
        $currentIndex = $steps->search(fn (WorkflowStep $s) => (int) $s->step_order === (int) $instance->current_step_order);

        if ($currentIndex === false) {
            return null;
        }

        $next = $steps->get($currentIndex + 1);
        if (! $next) {
            $instance->update([
                'status' => 'termine',
                'completed_at' => now(),
            ]);

            return null;
        }

        $to = $this->resolveStepAssignee($next, $document, $actor);
        $expectedAction = $next->expected_action ?? ExpectedAction::Consultation;

        DocumentTransmission::query()->create([
            'document_id' => $document->id,
            'workflow_instance_id' => $instance->id,
            'from_user_id' => $actor->id,
            'to_user_id' => $to->id,
            'expected_action' => $expectedAction,
            'folder' => $this->folderForAction($expectedAction),
            'status' => 'pending',
            'message' => $message ?? 'Étape suivante du circuit',
        ]);

        $instance->update(['current_step_order' => $next->step_order]);

        $fromStatus = $document->status;
        $this->transition($document, DocumentStatus::EnCircuit);

        $targetStatus = match ($expectedAction) {
            ExpectedAction::Visa => DocumentStatus::AViser,
            ExpectedAction::Validation => DocumentStatus::AValider,
            default => DocumentStatus::AConsulter,
        };
        $this->transition($document, $targetStatus);

        $document->expected_action = $expectedAction;
        $document->current_assignee_id = $to->id;
        $document->save();

        $this->recordAction(
            $document,
            $actor,
            WorkflowActionType::Transmettre,
            $fromStatus,
            $document->status,
            $message ?? 'Avancement circuit',
            $instance->id,
        );

        $this->notifyUser(
            $to,
            $document,
            'transmitted',
            sprintf('Circuit « %s » : document transmis pour %s.', $workflow->name, $expectedAction->label()),
            $actor->name,
        );

        return $document->fresh();
    }

    public function acknowledge(Document $document, User $user, ?string $comment = null): Document
    {
        return DB::transaction(function () use ($document, $user, $comment) {
            $from = $document->status;
            $to = $this->stateMachine->statusAfterAction(WorkflowActionType::PriseConnaissance, $from);
            $this->transition($document, $to);
            $document->save();

            DocumentTransmission::query()
                ->where('document_id', $document->id)
                ->where('to_user_id', $user->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'seen',
                    'seen_at' => now(),
                ]);

            $this->recordAction($document, $user, WorkflowActionType::PriseConnaissance, $from, $to, $comment);
            $this->audit->log('document.acknowledged', $document);

            return $document->fresh(['type', 'structure', 'author', 'currentAssignee']);
        });
    }

    public function putOnHold(Document $document, User $actor, ?string $comment = null): Document
    {
        return DB::transaction(function () use ($document, $actor, $comment) {
            return $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::MettreEnAttente,
                $comment,
                function (Document $doc) use ($actor, $comment) {
                    DocumentTransmission::query()
                        ->where('document_id', $doc->id)
                        ->where('to_user_id', $actor->id)
                        ->whereIn('status', ['pending', 'seen'])
                        ->update([
                            'folder' => ParapheurFolder::EnAttente->value,
                            'status' => 'pending',
                            'message' => $comment,
                        ]);
                }
            );
        });
    }

    public function classify(Document $document, User $actor, ?string $comment = null): Document
    {
        return DB::transaction(function () use ($document, $actor, $comment) {
            return $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::Classer,
                $comment,
                null,
                null,
                ParapheurFolder::Traites,
            );
        });
    }

    public function reassign(Document $document, User $from, User $to, ExpectedAction $expectedAction, ?string $message = null): Document
    {
        return DB::transaction(function () use ($document, $from, $to, $expectedAction, $message) {
            $fromStatus = $document->status;

            DocumentTransmission::query()
                ->where('document_id', $document->id)
                ->where('to_user_id', $from->id)
                ->whereIn('status', ['pending', 'seen'])
                ->update([
                    'status' => 'done',
                    'acted_at' => now(),
                ]);

            DocumentTransmission::query()->create([
                'document_id' => $document->id,
                'workflow_instance_id' => $document->workflowInstance?->id,
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'expected_action' => $expectedAction,
                'folder' => $this->folderForAction($expectedAction),
                'status' => 'pending',
                'message' => $message,
            ]);

            $this->transition($document, DocumentStatus::Transmis);

            $targetStatus = match ($expectedAction) {
                ExpectedAction::Visa => DocumentStatus::AViser,
                ExpectedAction::Validation => DocumentStatus::AValider,
                default => DocumentStatus::AConsulter,
            };
            $this->transition($document, $targetStatus);

            $document->expected_action = $expectedAction;
            $document->current_assignee_id = $to->id;
            $document->save();

            $this->recordAction(
                $document,
                $from,
                WorkflowActionType::Reaffecter,
                $fromStatus,
                $document->status,
                $message,
            );

            $this->audit->log('document.reassigned', $document, ['to_user_id' => $to->id]);

            $this->notifyUser(
                $to,
                $document,
                'transmitted',
                sprintf('%s vous a réaffecté un document.', $from->name),
                $from->name,
            );

            return $document->fresh(['type', 'structure', 'author', 'currentAssignee']);
        });
    }

    public function addComment(Document $document, User $user, string $body, string $kind = 'general'): Comment
    {
        if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
            throw new InvalidArgumentException('Document figé : commentaire impossible.');
        }

        $kind = in_array($kind, ['general', 'avis', 'observation', 'recommandation'], true) ? $kind : 'general';

        $comment = Comment::query()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'body' => $body,
        ]);

        $actionType = match ($kind) {
            'avis' => WorkflowActionType::Avis,
            'recommandation' => WorkflowActionType::Recommandation,
            default => WorkflowActionType::Commenter,
        };

        $this->recordAction(
            $document,
            $user,
            $actionType,
            $document->status,
            $document->status,
            $body,
        );

        $this->audit->log('document.commented', $document, [
            'comment_id' => $comment->id,
            'kind' => $kind,
        ]);

        if ($document->author_id && (int) $document->author_id !== (int) $user->id) {
            $label = match ($kind) {
                'avis' => 'un avis',
                'recommandation' => 'une recommandation',
                'observation' => 'une observation',
                default => 'un commentaire',
            };
            $this->notifyUser(
                User::query()->find($document->author_id),
                $document,
                'commented',
                sprintf('%s a ajouté %s sur le dossier.', $user->name, $label),
                $user->name,
            );
        }

        return $comment->load('user');
    }

    public function requestComplement(Document $document, User $actor, string $comment): Document
    {
        $result = DB::transaction(function () use ($document, $actor, $comment) {
            return $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::DemandeComplement,
                $comment,
                function (Document $doc) use ($actor, $comment) {
                    DocumentTransmission::query()->create([
                        'document_id' => $doc->id,
                        'from_user_id' => $actor->id,
                        'to_user_id' => $doc->author_id,
                        'expected_action' => ExpectedAction::Observations,
                        'folder' => ParapheurFolder::Retournes,
                        'status' => 'pending',
                        'message' => $comment,
                    ]);
                    $doc->current_assignee_id = $doc->author_id;
                }
            );
        });

        if ($document->author_id) {
            $this->notifyUser(
                User::query()->find($document->author_id),
                $document,
                'returned',
                sprintf('%s a demandé un complément sur le document.', $actor->name),
                $actor->name,
            );
        }

        return $result;
    }

    public function returnForCorrection(Document $document, User $actor, string $comment): Document
    {
        $result = DB::transaction(function () use ($document, $actor, $comment) {
            return $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::RetourCorrection,
                $comment,
                function (Document $doc) use ($actor, $comment) {
                    DocumentTransmission::query()->create([
                        'document_id' => $doc->id,
                        'from_user_id' => $actor->id,
                        'to_user_id' => $doc->author_id,
                        'expected_action' => ExpectedAction::Observations,
                        'folder' => ParapheurFolder::Retournes,
                        'status' => 'pending',
                        'message' => $comment,
                    ]);
                    $doc->current_assignee_id = $doc->author_id;
                }
            );
        });

        if ($document->author_id) {
            $this->notifyUser(
                User::query()->find($document->author_id),
                $document,
                'returned',
                sprintf('%s a retourné le document pour correction.', $actor->name),
                $actor->name,
            );
        }

        return $result;
    }

    public function vise(Document $document, User $actor, ?string $comment = null, ?User $delegator = null): Document
    {
        $delegator ??= $this->delegations->resolveDelegator($actor, $document, 'vise');

        return DB::transaction(function () use ($document, $actor, $comment, $delegator) {
            $result = $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::Viser,
                $comment,
                null,
                $delegator,
                ParapheurFolder::Traites,
            );

            Visa::query()->create([
                'document_id' => $document->id,
                'user_id' => $actor->id,
                'delegator_id' => $delegator?->id,
                'comment' => $comment,
                'vised_at' => now(),
            ]);

            $result = $result->fresh(['workflowInstance.workflow.steps', 'type', 'structure', 'author', 'currentAssignee', 'visas', 'approvals']);
            $advanced = $this->advancePredefinedWorkflow($result, $actor, $comment);
            if ($advanced) {
                return $advanced;
            }

            // Circuit libre ou fin de circuit : passage à traité si pas d'étape suivante
            if (! $result->workflowInstance || $result->workflowInstance->kind === 'libre'
                || $result->workflowInstance->status === 'termine') {
                if ($this->stateMachine->canTransition($result->status, DocumentStatus::Traite)) {
                    $from = $result->status;
                    $result->status = DocumentStatus::Traite;
                    $result->save();
                    $this->recordAction($result, $actor, WorkflowActionType::Viser, $from, DocumentStatus::Traite, 'Dossier traité après visa');
                }
            }

            if ($document->author_id && (int) $document->author_id !== (int) $actor->id) {
                $this->notifyUser(
                    User::query()->find($document->author_id),
                    $document,
                    'vised',
                    sprintf('%s a visé le document.', $actor->name),
                    $actor->name,
                );
            }

            return $result->fresh(['type', 'structure', 'author', 'currentAssignee', 'visas', 'approvals']);
        });
    }

    public function validateDocument(Document $document, User $actor, ?string $comment = null, ?User $delegator = null): Document
    {
        $delegator ??= $this->delegations->resolveDelegator($actor, $document, 'validate');

        return DB::transaction(function () use ($document, $actor, $comment, $delegator) {
            $result = $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::Valider,
                $comment,
                function (Document $doc) {
                    $doc->status = DocumentStatus::Valide;
                },
                $delegator,
                ParapheurFolder::Traites,
            );

            Approval::query()->create([
                'document_id' => $document->id,
                'user_id' => $actor->id,
                'delegator_id' => $delegator?->id,
                'decision' => 'valide',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            // Marquer la version principale comme version officielle validée.
            DocumentVersion::query()
                ->where('document_id', $document->id)
                ->where('is_main', true)
                ->update(['is_official' => true]);

            $suffix = $delegator
                ? sprintf(' (par délégation de %s)', $delegator->name)
                : '';

            if ($document->author_id && (int) $document->author_id !== (int) $actor->id) {
                $this->notifyUser(
                    User::query()->find($document->author_id),
                    $document,
                    'validated',
                    sprintf('%s a validé administrativement le document%s.', $actor->name, $suffix),
                    $actor->name,
                );
            }

            $result = $result->fresh(['workflowInstance.workflow.steps', 'type', 'structure', 'author', 'currentAssignee', 'visas', 'approvals']);
            $advanced = $this->advancePredefinedWorkflow($result, $actor, $comment);
            if ($advanced) {
                return $advanced;
            }

            if ($this->stateMachine->canTransition($result->status, DocumentStatus::Traite)) {
                $from = $result->status;
                $result->status = DocumentStatus::Traite;
                $result->save();
                $this->recordAction($result, $actor, WorkflowActionType::Valider, $from, DocumentStatus::Traite, 'Dossier traité après validation');

                if ($result->workflowInstance && $result->workflowInstance->kind === 'predefini') {
                    $result->workflowInstance->update([
                        'status' => 'termine',
                        'completed_at' => now(),
                    ]);
                }
            }

            $result = $result->fresh(['type', 'structure', 'author', 'currentAssignee', 'visas', 'approvals']);

            // P1 : classement automatique + règle de conservation
            try {
                app(DocumentAutoClassificationService::class)->applyAfterStatus($result, 'valide', $actor);
                app(DocumentRetentionService::class)->applyMatchingRule($result);
            } catch (\Throwable $e) {
                report($e);
            }

            return $result->fresh(['type', 'structure', 'author', 'currentAssignee', 'visas', 'approvals', 'classificationNode']);
        });
    }

    public function reject(Document $document, User $actor, string $comment): Document
    {
        return DB::transaction(function () use ($document, $actor, $comment) {
            $result = $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::Rejeter,
                $comment,
                null,
                null,
                ParapheurFolder::Traites,
            );

            Approval::query()->create([
                'document_id' => $document->id,
                'user_id' => $actor->id,
                'decision' => 'rejete',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            if ($document->author_id && (int) $document->author_id !== (int) $actor->id) {
                $this->notifyUser(
                    User::query()->find($document->author_id),
                    $document,
                    'rejected',
                    sprintf('%s a rejeté le document : %s', $actor->name, $comment),
                    $actor->name,
                );
            }

            return $result;
        });
    }

    public function archive(Document $document, User $actor, ?string $comment = null): Document
    {
        return DB::transaction(function () use ($document, $actor, $comment) {
            return $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::Archiver,
                $comment,
                function (Document $doc) use ($actor) {
                    $doc->archived_at = now();

                    if ($doc->author_id && (int) $doc->author_id !== (int) $actor->id) {
                        DocumentTransmission::query()->create([
                            'document_id' => $doc->id,
                            'from_user_id' => $actor->id,
                            'to_user_id' => $doc->author_id,
                            'expected_action' => ExpectedAction::Information,
                            'folder' => ParapheurFolder::Archives,
                            'status' => 'done',
                            'acted_at' => now(),
                            'message' => 'Document archivé',
                        ]);
                    }
                },
                null,
                ParapheurFolder::Archives,
            );
        });
    }

    public function addVersion(Document $document, User $user, UploadedFile $file, ?string $note = null): DocumentVersion
    {
        return DB::transaction(function () use ($document, $user, $file, $note) {
            if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
                throw new InvalidArgumentException('Document figé : nouvelle version impossible.');
            }

            $version = $this->storeMainVersion($document, $user, $file, $note);
            $document->current_version = $version->version_number;

            if ($document->status === DocumentStatus::ACorriger) {
                $this->transition($document, DocumentStatus::Corrige);
            }

            $document->save();
            $this->audit->log('document.version_added', $document, ['version' => $version->version_number]);

            return $version;
        });
    }

    /**
     * Nouvelle version immuable à partir d’un contenu binaire (callback ONLYOFFICE).
     */
    public function addVersionFromContents(
        Document $document,
        User $user,
        string $contents,
        string $originalName,
        string $mimeType,
        ?string $note = null,
    ): DocumentVersion {
        return DB::transaction(function () use ($document, $user, $contents, $originalName, $mimeType, $note) {
            if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
                throw new InvalidArgumentException('Document figé : nouvelle version impossible.');
            }

            $stored = $this->storage->storeContent(
                $contents,
                $originalName,
                $mimeType,
                'documents/'.$document->uuid,
            );
            $next = ((int) $document->versions()->max('version_number')) + 1;

            $document->versions()->where('is_main', true)->update(['is_main' => false]);

            $version = DocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => $next,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size' => $stored['size'],
                'checksum' => $stored['checksum'],
                'is_main' => true,
                'is_official' => false,
                'uploaded_by' => $user->id,
                'change_note' => $note,
                'change_source' => 'onlyoffice',
            ]);

            $document->current_version = $version->version_number;

            if ($document->status === DocumentStatus::ACorriger) {
                $this->transition($document, DocumentStatus::Corrige);
            }

            $document->save();
            $this->audit->log('document.version_added', $document, [
                'version' => $version->version_number,
                'source' => 'onlyoffice',
            ]);

            return $version;
        });
    }

    public function addAttachment(Document $document, User $user, UploadedFile $file, string $kind = 'piece_jointe'): DocumentAttachment
    {
        if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
            throw new InvalidArgumentException('Document figé : pièce jointe impossible.');
        }

        $attachment = $this->storeAttachment($document, $user, $file, $kind);
        $this->audit->log('document.attachment_added', $document, ['attachment_id' => $attachment->id]);

        return $attachment;
    }

    public function createInstructionFromComment(
        Document $document,
        Comment $comment,
        User $issuer,
        User $assignee,
        array $data,
    ): Instruction {
        if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
            throw new InvalidArgumentException('Document figé : instruction impossible.');
        }
        $instruction = Instruction::query()->create([
            'reference' => app(NumberingService::class)->nextNumber(\App\Enums\NumberingSequenceCode::Instruction),
            'document_id' => $document->id,
            'issuer_id' => $issuer->id,
            'assignee_id' => $assignee->id,
            'structure_id' => $data['structure_id'] ?? $assignee->structure_id,
            'title' => $data['title'] ?? 'Instruction DG',
            'body' => $data['body'] ?? $comment->body,
            'priority' => $data['priority'] ?? 'importante',
            'source_kind' => \App\Enums\TaskSource::Parapheur->value,
            'source_type' => Document::class,
            'source_id' => $document->id,
            'status' => 'a_faire',
            'due_date' => $data['due_date'] ?? null,
        ]);

        app(\App\Services\Tasks\InstructionService::class)->addExecutionTask($issuer, $instruction, [
            'title' => $instruction->title,
            'description' => $instruction->body,
            'assignee_id' => $assignee->id,
            'structure_id' => $instruction->structure_id,
            'priority' => $instruction->priority?->value ?? 'importante',
            'due_at' => $instruction->due_date?->endOfDay(),
        ]);

        $comment->update(['is_instruction_source' => true]);

        $this->recordAction(
            $document,
            $issuer,
            WorkflowActionType::Instruction,
            $document->status,
            $document->status,
            $instruction->body,
        );

        $this->audit->log('instruction.created', $instruction, ['document_id' => $document->id]);

        $this->notifyUser(
            $assignee,
            $document,
            'instruction',
            sprintf('%s vous a assigné une instruction : %s', $issuer->name, $instruction->title),
            $issuer->name,
        );

        return $instruction->load(['assignee', 'issuer', 'document']);
    }

    private function applyTerminalAction(
        Document $document,
        User $actor,
        WorkflowActionType $action,
        ?string $comment = null,
        ?callable $mutator = null,
        ?User $delegator = null,
        ?ParapheurFolder $doneFolder = null,
    ): Document {
        $from = $document->status;
        $to = $this->stateMachine->statusAfterAction($action, $from);

        // Applique le chemin métier (étapes intermédiaires) jusqu’au statut cible.
        $this->transition($document, $to);

        if ($mutator) {
            $mutator($document);
        }
        $document->save();

        if ($action === WorkflowActionType::MettreEnAttente) {
            // folder déjà mis à jour dans le mutator
        } elseif ($doneFolder) {
            $this->closeActorTransmissions($document, $actor, $doneFolder);
        } else {
            DocumentTransmission::query()
                ->where('document_id', $document->id)
                ->where('to_user_id', $actor->id)
                ->whereIn('status', ['pending', 'seen'])
                ->update([
                    'status' => 'done',
                    'acted_at' => now(),
                ]);
        }

        $this->recordAction($document, $actor, $action, $from, $to, $comment, null, $delegator);
        $this->audit->log('document.action.'.$action->value, $document, [
            'from' => $from->value,
            'to' => $to->value,
            'path' => array_map(
                fn (DocumentStatus $status) => $status->value,
                $this->stateMachine->path($from, $to) ?? [$from, $to],
            ),
            'delegator_id' => $delegator?->id,
        ]);

        return $document->fresh(['type', 'structure', 'author', 'currentAssignee', 'visas', 'approvals', 'workflowInstance']);
    }

    private function closeActorTransmissions(Document $document, User $actor, ParapheurFolder $doneFolder): void
    {
        DocumentTransmission::query()
            ->where('document_id', $document->id)
            ->where('to_user_id', $actor->id)
            ->whereIn('status', ['pending', 'seen'])
            ->update([
                'status' => 'done',
                'acted_at' => now(),
                'folder' => $doneFolder->value,
            ]);

        // Garantir une entrée dans la corbeille traites/archives
        $exists = DocumentTransmission::query()
            ->where('document_id', $document->id)
            ->where('to_user_id', $actor->id)
            ->where('folder', $doneFolder->value)
            ->where('status', 'done')
            ->exists();

        if (! $exists) {
            DocumentTransmission::query()->create([
                'document_id' => $document->id,
                'from_user_id' => $actor->id,
                'to_user_id' => $actor->id,
                'expected_action' => $document->expected_action ?? ExpectedAction::Consultation,
                'folder' => $doneFolder,
                'status' => 'done',
                'acted_at' => now(),
            ]);
        }
    }

    private function transition(Document $document, DocumentStatus $to): void
    {
        if ($document->status === $to) {
            return;
        }

        $steps = $this->stateMachine->stepsTo($document->status, $to);
        foreach ($steps as $step) {
            $this->stateMachine->assertCanTransition($document->status, $step);
            $document->status = $step;
        }
    }

    private function resolveStepAssignee(WorkflowStep $step, Document $document, User $from): User
    {
        $query = User::query()->where('is_active', true);

        if ($step->role_name) {
            $query->role($step->role_name);
        }

        if ($step->structure_id) {
            $query->where('structure_id', $step->structure_id);
        } elseif ($document->structure_id && $step->role_name && ! in_array($step->role_name, ['Directeur Général', 'DGA', 'Secrétariat DG', 'Conseiller'], true)) {
            $query->where('structure_id', $document->structure_id);
        }

        $user = $query->orderBy('id')->first();

        if (! $user && $step->role_name) {
            $user = User::query()->role($step->role_name)->where('is_active', true)->orderBy('id')->first();
        }

        if (! $user) {
            throw new InvalidArgumentException(
                "Aucun utilisateur trouvé pour l'étape « {$step->name} » (rôle : {$step->role_name})."
            );
        }

        if ((int) $user->id === (int) $from->id) {
            $alt = User::query()
                ->where('is_active', true)
                ->when($step->role_name, fn ($q) => $q->role($step->role_name))
                ->where('id', '!=', $from->id)
                ->orderBy('id')
                ->first();
            if ($alt) {
                return $alt;
            }
        }

        return $user;
    }

    private function recordAction(
        Document $document,
        User $actor,
        WorkflowActionType $type,
        DocumentStatus $from,
        DocumentStatus $to,
        ?string $comment = null,
        ?int $instanceId = null,
        ?User $delegator = null,
    ): void {
        $document->actions()->create([
            'workflow_instance_id' => $instanceId ?? $document->workflowInstance?->id,
            'actor_id' => $actor->id,
            'delegator_id' => $delegator?->id,
            'action_type' => $type,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'comment' => $comment,
        ]);
    }

    private function storeMainVersion(Document $document, User $user, UploadedFile $file, ?string $note): DocumentVersion
    {
        $stored = $this->storage->store($file, 'documents/'.$document->uuid);
        $next = ((int) $document->versions()->max('version_number')) + 1;

        $document->versions()->where('is_main', true)->update(['is_main' => false]);

        return DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => $next,
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'checksum' => $stored['checksum'],
            'is_main' => true,
            'is_official' => false,
            'uploaded_by' => $user->id,
            'change_note' => $note,
            'change_source' => 'upload',
        ]);
    }

    private function storeAttachment(Document $document, User $user, UploadedFile $file, string $kind = 'piece_jointe'): DocumentAttachment
    {
        $stored = $this->storage->store($file, 'documents/'.$document->uuid.'/attachments');

        return DocumentAttachment::query()->create([
            'document_id' => $document->id,
            'kind' => $kind,
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'uploaded_by' => $user->id,
        ]);
    }

    private function folderForAction(ExpectedAction $action): ParapheurFolder
    {
        return match ($action) {
            ExpectedAction::Information => ParapheurFolder::PourInformation,
            ExpectedAction::Consultation, ExpectedAction::Avis, ExpectedAction::Observations, ExpectedAction::Instruction, ExpectedAction::Revision => ParapheurFolder::AConsulter,
            ExpectedAction::Visa => ParapheurFolder::AViser,
            ExpectedAction::Validation, ExpectedAction::Signature => ParapheurFolder::AValider,
        };
    }

    private function generateReference(User $author): string
    {
        $structure = $author->structure?->code ?? 'ORG';

        return sprintf('%s/%s/%04d', $structure, now()->format('Y'), random_int(1, 9999));
    }

    private function notifyUser(?User $user, Document $document, string $event, string $message, ?string $actorName = null): void
    {
        if (! $user) {
            return;
        }

        $payload = [
            'document' => $document->withoutRelations(),
            'event' => $event,
            'message' => $message,
            'actorName' => $actorName,
            'userId' => $user->id,
        ];

        $send = function () use ($payload) {
            $user = User::query()->find($payload['userId']);
            if (! $user) {
                return;
            }

            $notification = new DocumentWorkflowNotification(
                $payload['document'],
                $payload['event'],
                $payload['message'],
                $payload['actorName'],
            );

            try {
                $user->notify($notification);
            } catch (\Throwable $e) {
                report($e);

                // L’échec SMTP ne doit pas faire perdre la notif in-app
                try {
                    $user->notify(
                        (new DocumentWorkflowNotification(
                            $payload['document'],
                            $payload['event'],
                            $payload['message'],
                            $payload['actorName'],
                        ))->viaChannels(['database'])
                    );
                } catch (\Throwable $fallbackError) {
                    report($fallbackError);
                }
            }
        };

        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            \Illuminate\Support\Facades\DB::afterCommit($send);
        } else {
            $send();
        }
    }
}
