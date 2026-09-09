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
use App\Models\WorkflowInstance;
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
    ) {}

    public function createDraft(User $author, array $data, ?UploadedFile $mainFile = null, array $attachments = []): Document
    {
        return DB::transaction(function () use ($author, $data, $mainFile, $attachments) {
            $document = Document::query()->create([
                'uuid' => (string) Str::uuid(),
                'reference' => $data['reference'] ?? $this->generateReference($author),
                'object' => $data['object'],
                'document_type_id' => $data['document_type_id'],
                'structure_id' => $data['structure_id'] ?? $author->structure_id,
                'author_id' => $author->id,
                'status' => DocumentStatus::Brouillon,
                'priority' => $data['priority'] ?? 'normale',
                'confidentiality' => $data['confidentiality'] ?? 'normal',
                'expected_action' => $data['expected_action'] ?? ExpectedAction::Consultation->value,
                'document_date' => $data['document_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'keywords' => $data['keywords'] ?? [],
                'current_version' => $mainFile ? 1 : 0,
            ]);

            if ($mainFile) {
                $this->storeMainVersion($document, $author, $mainFile, 'Version initiale');
            }

            foreach ($attachments as $attachment) {
                $this->storeAttachment($document, $author, $attachment);
            }

            $this->audit->log('document.created', $document);

            return $document->fresh(['type', 'structure', 'author', 'latestVersion', 'attachments']);
        });
    }

    public function submitAndTransmit(
        Document $document,
        User $from,
        User $to,
        ExpectedAction $expectedAction,
        ?string $message = null,
    ): Document {
        return DB::transaction(function () use ($document, $from, $to, $expectedAction, $message) {
            if ($document->status === DocumentStatus::Brouillon) {
                $this->stateMachine->assertCanTransition($document->status, DocumentStatus::Depose);
                $document->status = DocumentStatus::Depose;
                $document->submitted_at = now();
                $document->save();
            }

            $instance = WorkflowInstance::query()->create([
                'document_id' => $document->id,
                'workflow_id' => null,
                'kind' => 'libre',
                'status' => 'en_cours',
                'current_step_order' => 1,
                'started_by' => $from->id,
            ]);

            $folder = $this->folderForAction($expectedAction);

            DocumentTransmission::query()->create([
                'document_id' => $document->id,
                'workflow_instance_id' => $instance->id,
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'expected_action' => $expectedAction,
                'folder' => $folder,
                'status' => 'pending',
                'message' => $message,
            ]);

            $fromStatus = $document->status;
            $targetStatus = match ($expectedAction) {
                ExpectedAction::Visa => DocumentStatus::AViser,
                ExpectedAction::Validation => DocumentStatus::AValider,
                ExpectedAction::Information => DocumentStatus::AConsulter,
                default => DocumentStatus::AConsulter,
            };

            if ($this->stateMachine->canTransition($document->status, DocumentStatus::Transmis)) {
                $document->status = DocumentStatus::Transmis;
            }
            if ($this->stateMachine->canTransition($document->status, $targetStatus)) {
                $document->status = $targetStatus;
            } else {
                $document->status = $targetStatus;
            }

            $document->expected_action = $expectedAction;
            $document->current_assignee_id = $to->id;
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
                'to_user_id' => $to->id,
                'expected_action' => $expectedAction->value,
            ]);

            return $document->fresh(['type', 'structure', 'author', 'currentAssignee', 'latestVersion']);
        });
    }

    public function addComment(Document $document, User $user, string $body, string $kind = 'general'): Comment
    {
        $comment = Comment::query()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'kind' => $kind,
            'body' => $body,
        ]);

        $this->recordAction(
            $document,
            $user,
            WorkflowActionType::Commenter,
            $document->status,
            $document->status,
            $body,
        );

        $this->audit->log('document.commented', $document, ['comment_id' => $comment->id]);

        return $comment->load('user');
    }

    public function returnForCorrection(Document $document, User $actor, string $comment): Document
    {
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
    }

    public function vise(Document $document, User $actor, ?string $comment = null, ?User $delegator = null): Document
    {
        return DB::transaction(function () use ($document, $actor, $comment, $delegator) {
            $result = $this->applyTerminalAction(
                $document,
                $actor,
                WorkflowActionType::Viser,
                $comment,
                null,
                $delegator,
            );

            Visa::query()->create([
                'document_id' => $document->id,
                'user_id' => $actor->id,
                'delegator_id' => $delegator?->id,
                'comment' => $comment,
                'vised_at' => now(),
            ]);

            return $result;
        });
    }

    public function validateDocument(Document $document, User $actor, ?string $comment = null, ?User $delegator = null): Document
    {
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
            );

            Approval::query()->create([
                'document_id' => $document->id,
                'user_id' => $actor->id,
                'delegator_id' => $delegator?->id,
                'decision' => 'valide',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            return $result;
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
            );

            Approval::query()->create([
                'document_id' => $document->id,
                'user_id' => $actor->id,
                'decision' => 'rejete',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            return $result;
        });
    }

    public function archive(Document $document, User $actor, ?string $comment = null): Document
    {
        return $this->applyTerminalAction(
            $document,
            $actor,
            WorkflowActionType::Archiver,
            $comment,
            function (Document $doc) {
                $doc->archived_at = now();
            }
        );
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
                $this->stateMachine->assertCanTransition($document->status, DocumentStatus::Corrige);
                $document->status = DocumentStatus::Corrige;
            }

            $document->save();
            $this->audit->log('document.version_added', $document, ['version' => $version->version_number]);

            return $version;
        });
    }

    public function createInstructionFromComment(
        Document $document,
        Comment $comment,
        User $issuer,
        User $assignee,
        array $data,
    ): Instruction {
        $instruction = Instruction::query()->create([
            'document_id' => $document->id,
            'issuer_id' => $issuer->id,
            'assignee_id' => $assignee->id,
            'structure_id' => $data['structure_id'] ?? $assignee->structure_id,
            'title' => $data['title'] ?? 'Instruction DG',
            'body' => $data['body'] ?? $comment->body,
            'priority' => $data['priority'] ?? 'importante',
            'status' => 'a_faire',
            'due_date' => $data['due_date'] ?? null,
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

        return $instruction->load(['assignee', 'issuer', 'document']);
    }

    private function applyTerminalAction(
        Document $document,
        User $actor,
        WorkflowActionType $action,
        ?string $comment = null,
        ?callable $mutator = null,
        ?User $delegator = null,
    ): Document {
        return DB::transaction(function () use ($document, $actor, $action, $comment, $mutator, $delegator) {
            $from = $document->status;
            $to = $this->stateMachine->statusAfterAction($action, $from);
            $this->stateMachine->assertCanTransition($from, $to);

            $document->status = $to;
            if ($mutator) {
                $mutator($document);
            }
            $document->save();

            DocumentTransmission::query()
                ->where('document_id', $document->id)
                ->where('to_user_id', $actor->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'done',
                    'acted_at' => now(),
                ]);

            $this->recordAction($document, $actor, $action, $from, $to, $comment, null, $delegator);
            $this->audit->log('document.action.'.$action->value, $document, [
                'from' => $from->value,
                'to' => $to->value,
                'delegator_id' => $delegator?->id,
            ]);

            return $document->fresh(['type', 'structure', 'author', 'currentAssignee', 'visas', 'approvals']);
        });
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
            'uploaded_by' => $user->id,
            'change_note' => $note,
        ]);
    }

    private function storeAttachment(Document $document, User $user, UploadedFile $file): DocumentAttachment
    {
        $stored = $this->storage->store($file, 'documents/'.$document->uuid.'/attachments');

        return DocumentAttachment::query()->create([
            'document_id' => $document->id,
            'kind' => 'piece_jointe',
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
            ExpectedAction::Consultation, ExpectedAction::Avis, ExpectedAction::Observations, ExpectedAction::Instruction => ParapheurFolder::AConsulter,
            ExpectedAction::Visa => ParapheurFolder::AViser,
            ExpectedAction::Validation => ParapheurFolder::AValider,
        };
    }

    private function generateReference(User $author): string
    {
        $structure = $author->structure?->code ?? 'DGTCP';

        return sprintf('%s/%s/%04d', $structure, now()->format('Y'), random_int(1, 9999));
    }
}
