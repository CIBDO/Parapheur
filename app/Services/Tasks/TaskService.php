<?php

namespace App\Services\Tasks;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\NumberingSequenceCode;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Events\TaskCreated;
use App\Models\AuditLog;
use App\Models\ProductionObligation;
use App\Models\ProductionOccurrence;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskHistory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NumberingService;
use App\Services\PrivateDocumentStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly AuditLogger $audit,
        private readonly TaskAccessService $access,
        private readonly TaskStateMachine $stateMachine,
        private readonly TaskNotificationService $notifications,
        private readonly PrivateDocumentStorage $storage,
        private readonly TaskReminderService $reminders,
        private readonly TaskScopeResolver $scope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Task::query()
            ->with(['assignee:id,name', 'creator:id,name', 'structure:id,code,name', 'instruction:id,reference,title'])
            ->visibleTo($actor);

        if (! empty($filters['mine'])) {
            $query->mine($actor);
        }
        if (! empty($filters['assigned_by_me'])) {
            $query->assignedBy($actor);
        }
        if (! empty($filters['to_validate'])) {
            $query->toValidate($actor);
        }
        if (! empty($filters['overdue'])) {
            $query->overdue();
        }
        if (! empty($filters['team'])) {
            if ($actor->can('task.view_team') || $this->scope->isGlobal($actor)) {
                if (empty($filters['structure_id'])) {
                    $structureIds = $this->scope->structureIds($actor);
                    if (! $this->scope->isGlobal($actor)) {
                        if ($structureIds === []) {
                            $query->whereRaw('1 = 0');
                        } else {
                            $query->whereIn('structure_id', $structureIds);
                        }
                    }
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }
        if (! empty($filters['creator_id'])) {
            $query->where('created_by', $filters['creator_id']);
        }
        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', $filters['structure_id']);
        }
        if (! empty($filters['source_kind'])) {
            $query->where('source_kind', $filters['source_kind']);
        }
        if (! empty($filters['confidentiality'])) {
            $query->where('confidentiality', $filters['confidentiality']);
        }
        if (! empty($filters['instruction_id'])) {
            $query->where('instruction_id', $filters['instruction_id']);
        }
        if (! empty($filters['tag'])) {
            $tag = $filters['tag'];
            $query->whereJsonContains('tags', $tag);
        }
        if (! empty($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        } elseif (empty($filters['include_subtasks'])) {
            $query->whereNull('parent_id');
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('reference', 'like', $q)
                    ->orWhere('title', 'like', $q)
                    ->orWhere('description', 'like', $q)
                    ->orWhereHas('assignee', fn ($a) => $a->where('name', 'like', $q))
                    ->orWhereHas('creator', fn ($a) => $a->where('name', 'like', $q))
                    ->orWhereHas('contributors', fn ($a) => $a->where('users.name', 'like', $q));
            });
        }
        if (! empty($filters['due_from'])) {
            $query->whereDate('due_at', '>=', $filters['due_from']);
        }
        if (! empty($filters['due_to'])) {
            $query->whereDate('due_at', '<=', $filters['due_to']);
        }
        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }
        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        $allowedSorts = ['due_at', 'created_at', 'updated_at', 'priority', 'status', 'title', 'id'];
        $sort = in_array(($filters['sort'] ?? 'created_at'), $allowedSorts, true)
            ? ($filters['sort'] ?? 'created_at')
            : 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if ($sort === 'due_at') {
            $query->orderByRaw('due_at is null')->orderBy('due_at', $dir)->orderByDesc('id');
        } else {
            $query->orderBy($sort, $dir)->orderByDesc('id');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findFor(User $actor, Task $task): Task
    {
        if (! $this->access->canView($actor, $task)) {
            abort(403, 'Accès refusé à cette tâche.');
        }

        return $task->load([
            'assignee:id,name,email',
            'creator:id,name,email',
            'validator:id,name',
            'structure:id,code,name',
            'instruction:id,reference,title,status',
            'parent:id,reference,title,status',
            'contributors:id,name',
            'subtasks.assignee:id,name',
            'comments.user:id,name',
            'attachments.uploader:id,name',
            'histories.user:id,name',
            'completions.user:id,name',
            'validations.validator:id,name',
            'dependencies.relatedTask:id,reference,title,status,due_at',
            'documentLinks.document',
            'documentLinks.document.type',
            'documentLinks.document.latestVersion',
            'documentLinks.document.classificationNode',
            'documentLinks.linker:id,name',
        ]);
    }

    /**
     * Création manuelle par un agent : le périmètre de collaboration s'applique.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Task
    {
        return DB::transaction(function () use ($actor, $data) {
            $this->assertCollaboratorsInScope($actor, $data);

            return $this->persistCreatedTask($actor, $data, [
                'context' => 'manual',
                'history_comment' => 'Création de la tâche',
            ]);
        });
    }

    /**
     * Création système pour une occurrence de production automatique.
     *
     * Contourne canAssignTo() (périmètre de collaboration) tout en n'autorisant
     * que les acteurs configurés sur une obligation active — sans ouvrir les
     * droits de collaboration entre structures.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromProduction(
        User $systemActor,
        ProductionObligation $obligation,
        ProductionOccurrence $occurrence,
        array $data = [],
    ): Task {
        return DB::transaction(function () use ($systemActor, $obligation, $occurrence, $data) {
            $payload = $this->buildProductionTaskPayload(
                $systemActor,
                $obligation,
                $occurrence,
                $data
            );

            $this->assertProductionSystemAssignees($systemActor, $obligation, $occurrence, $payload);

            return $this->persistCreatedTask($systemActor, $payload, [
                'context' => 'production_system',
                'history_comment' => 'Création système (production automatique)',
                'obligation_id' => $obligation->id,
                'occurrence_id' => $occurrence->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertCollaboratorsInScope(User $actor, array $data): void
    {
        $assigneeId = $data['assignee_id'] ?? null;

        if ($assigneeId) {
            $assignee = User::query()->findOrFail((int) $assigneeId);
            if (! $this->access->canAssignTo($actor, $assignee)) {
                abort(403, 'Cet utilisateur est hors de votre périmètre de collaboration.');
            }
        }

        foreach ($data['contributor_ids'] ?? [] as $contributorId) {
            $contributor = User::query()->find((int) $contributorId);
            if ($contributor && ! $this->access->canAssignTo($actor, $contributor)) {
                abort(403, 'Un contributeur est hors de votre périmètre de collaboration.');
            }
        }

        if (! empty($data['validator_id'])) {
            $validator = User::query()->find((int) $data['validator_id']);
            if ($validator && ! $this->access->canAssignTo($actor, $validator)) {
                abort(403, 'Le validateur est hors de votre périmètre de collaboration.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildProductionTaskPayload(
        User $systemActor,
        ProductionObligation $obligation,
        ProductionOccurrence $occurrence,
        array $data,
    ): array {
        if (! $obligation->is_active) {
            throw new InvalidArgumentException(
                'Seules les obligations de production actives peuvent générer des tâches système.'
            );
        }

        if ((int) $occurrence->production_obligation_id !== (int) $obligation->id) {
            throw new InvalidArgumentException(
                'L\'occurrence ne correspond pas à l\'obligation de production.'
            );
        }

        $validatorId = $data['validator_id']
            ?? $obligation->validators()->orderBy('step_order')->value('user_id')
            ?? $systemActor->id;

        return array_merge($data, [
            'title' => $data['title'] ?? ($obligation->label.' — '.optional($occurrence->reference_date)->format('d/m/Y')),
            'description' => $data['description'] ?? $obligation->description,
            'assignee_id' => $data['assignee_id'] ?? $occurrence->responsible_id ?? $obligation->producer_id,
            'structure_id' => $data['structure_id'] ?? $obligation->structure_id ?? $systemActor->structure_id,
            'validator_id' => $validatorId,
            'contributor_ids' => $data['contributor_ids'] ?? [],
            'due_at' => $data['due_at'] ?? $occurrence->deadline_at,
            'confidentiality' => $data['confidentiality']
                ?? $obligation->confidentiality?->value
                ?? $obligation->confidentiality
                ?? DocumentConfidentiality::Normal->value,
            'source_kind' => TaskSource::Production->value,
            'source_type' => ProductionOccurrence::class,
            'source_id' => $occurrence->id,
            'as_draft' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertProductionSystemAssignees(
        User $systemActor,
        ProductionObligation $obligation,
        ProductionOccurrence $occurrence,
        array $data,
    ): void {
        $allowedAssigneeIds = array_values(array_unique(array_filter([
            $obligation->producer_id ? (int) $obligation->producer_id : null,
            $obligation->deputy_id ? (int) $obligation->deputy_id : null,
            $occurrence->responsible_id ? (int) $occurrence->responsible_id : null,
        ])));

        $assigneeId = isset($data['assignee_id']) ? (int) $data['assignee_id'] : null;
        if (! $assigneeId || ! in_array($assigneeId, $allowedAssigneeIds, true)) {
            abort(422, 'Le responsable de la tâche de production doit être le producteur (ou suppléant) configuré sur l\'obligation.');
        }

        User::query()->findOrFail($assigneeId);

        $allowedValidatorIds = $obligation->validators()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $allowedValidatorIds = array_values(array_unique(array_filter(array_merge(
            $allowedValidatorIds,
            $allowedAssigneeIds,
            [(int) $systemActor->id],
        ))));

        $validatorId = isset($data['validator_id']) ? (int) $data['validator_id'] : null;
        if ($validatorId && ! in_array($validatorId, $allowedValidatorIds, true)) {
            abort(422, 'Le validateur de la tâche de production doit être configuré sur l\'obligation.');
        }

        if ($validatorId) {
            User::query()->findOrFail($validatorId);
        }

        $allowedContributorIds = $obligation->contributors()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $allowedRecipientIds = $obligation->recipients()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $allowedParticipantIds = array_values(array_unique(array_merge(
            $allowedContributorIds,
            $allowedRecipientIds,
            $allowedAssigneeIds,
            $allowedValidatorIds,
        )));

        foreach ($data['contributor_ids'] ?? [] as $contributorId) {
            $id = (int) $contributorId;
            if (! in_array($id, $allowedParticipantIds, true)) {
                abort(422, 'Un contributeur de la tâche de production n\'est pas configuré sur l\'obligation.');
            }
            User::query()->findOrFail($id);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    private function persistCreatedTask(User $actor, array $data, array $meta = []): Task
    {
        $asDraft = (bool) ($data['as_draft'] ?? false);
        $assigneeId = $data['assignee_id'] ?? null;

        $status = $asDraft || ! $assigneeId
            ? TaskStatus::Brouillon
            : TaskStatus::Imputee;

        $task = Task::query()->create([
            'reference' => $this->numbering->nextNumber(NumberingSequenceCode::Task),
            'parent_id' => $data['parent_id'] ?? null,
            'instruction_id' => $data['instruction_id'] ?? null,
            'task_type_id' => $data['task_type_id'] ?? null,
            'created_by' => $actor->id,
            'assignee_id' => $assigneeId,
            'structure_id' => $data['structure_id'] ?? $actor->structure_id,
            'validator_id' => $data['validator_id'] ?? $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $status,
            'priority' => $data['priority'] ?? DocumentPriority::Normale->value,
            'confidentiality' => $data['confidentiality'] ?? DocumentConfidentiality::Normal->value,
            'source_kind' => $data['source_kind'] ?? TaskSource::Manual->value,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'progress' => (int) ($data['progress'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'is_personal' => (bool) ($data['is_personal'] ?? false),
            'tags' => $data['tags'] ?? null,
        ]);

        $this->syncContributors($task, $data['contributor_ids'] ?? []);

        $historyComment = (string) ($meta['history_comment'] ?? 'Création de la tâche');
        $historyProps = array_filter([
            'creation_context' => $meta['context'] ?? null,
            'obligation_id' => $meta['obligation_id'] ?? null,
            'occurrence_id' => $meta['occurrence_id'] ?? null,
        ], fn ($value) => $value !== null);
        $this->recordHistory(
            $task,
            $actor,
            'created',
            null,
            $status->value,
            $historyComment,
            $historyProps
        );
        $this->audit->log('task.created', $task, array_filter([
            'actor_id' => $actor->id,
            'creation_context' => $meta['context'] ?? null,
            'obligation_id' => $meta['obligation_id'] ?? null,
            'occurrence_id' => $meta['occurrence_id'] ?? null,
        ], fn ($value) => $value !== null));

        if ($status === TaskStatus::Imputee && $task->assignee_id) {
            $this->notifications->notifyAssigned($task, $actor);
        }

        $this->reminders->scheduleFor($task);
        event(new TaskCreated($task, $actor));

        return $task->fresh(['assignee', 'creator', 'contributors']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Task $task, array $data): Task
    {
        if (! $this->access->canUpdate($actor, $task)) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $task, $data) {
            $allowed = [
                'title', 'description', 'priority', 'confidentiality', 'structure_id',
                'validator_id', 'starts_at', 'due_at', 'progress', 'tags', 'is_personal',
            ];
            $payload = array_intersect_key($data, array_flip($allowed));
            $task->fill($payload)->save();

            if (array_key_exists('contributor_ids', $data)) {
                $this->syncContributors($task, $data['contributor_ids'] ?? []);
            }

            $this->recordHistory($task, $actor, 'updated', $task->status->value, $task->status->value, 'Mise à jour');
            $this->audit->log('task.updated', $task, ['actor_id' => $actor->id]);
            $this->reminders->scheduleFor($task);

            return $task->fresh(['assignee', 'creator', 'contributors']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSubtask(User $actor, Task $parent, array $data): Task
    {
        if ($parent->parent_id) {
            abort(422, 'Profondeur de sous-tâches limitée à un niveau. Ajoutez-la sur la tâche parente.');
        }

        if ($parent->status?->isTerminal()) {
            abort(422, 'Impossible d’ajouter une sous-tâche à une tâche clôturée.');
        }

        $data['parent_id'] = $parent->id;
        $data['instruction_id'] = $data['instruction_id'] ?? $parent->instruction_id;
        $data['source_kind'] = $data['source_kind'] ?? TaskSource::Manual->value;
        $data['assignee_id'] = $data['assignee_id'] ?? $parent->assignee_id;
        $data['structure_id'] = $data['structure_id'] ?? $parent->structure_id;
        $data['validator_id'] = $data['validator_id'] ?? $parent->validator_id ?? $parent->created_by;
        $data['priority'] = $data['priority'] ?? $parent->priority?->value ?? $parent->priority;
        $data['confidentiality'] = $data['confidentiality']
            ?? $parent->confidentiality?->value
            ?? $parent->confidentiality;
        $data['due_at'] = $data['due_at'] ?? optional($parent->due_at)?->toIso8601String();

        $subtask = $this->create($actor, $data);

        $this->recordHistory(
            $parent,
            $actor,
            'subtask_created',
            $parent->status->value,
            $parent->status->value,
            $subtask->reference.' — '.$subtask->title,
            ['subtask_id' => $subtask->id]
        );

        return $subtask;
    }

    public function addComment(User $actor, Task $task, string $body): TaskComment
    {
        if (! $this->access->canComment($actor, $task)) {
            abort(403);
        }

        $mentionIds = $this->extractMentionUserIds($body, $actor);

        $comment = $task->comments()->create([
            'user_id' => $actor->id,
            'body' => $body,
            'mentions' => $mentionIds ?: null,
        ]);

        $this->recordHistory($task, $actor, 'commented', $task->status->value, $task->status->value, $body, [
            'mentions' => $mentionIds,
        ]);
        $this->notifications->notifyComment($task, $actor, $body);
        if ($mentionIds !== []) {
            $this->notifications->notifyMentions($task, $actor, $body, $mentionIds);
        }
        $this->audit->log('task.commented', $task, [
            'actor_id' => $actor->id,
            'comment_id' => $comment->id,
            'mentions' => $mentionIds,
        ]);

        return $comment->load('user:id,name');
    }

    /**
     * @return list<int>
     */
    public function extractMentionUserIds(string $body, ?User $actor = null): array
    {
        // Formats : @42 ou @"Nom Complet"
        preg_match_all('/@(\d+)\b/', $body, $idMatches);
        $ids = array_map('intval', $idMatches[1] ?? []);

        preg_match_all('/@"([^"]+)"/', $body, $nameMatches);
        foreach ($nameMatches[1] ?? [] as $name) {
            $userId = User::query()->where('name', $name)->value('id');
            if ($userId) {
                $ids[] = (int) $userId;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($actor === null || $ids === []) {
            return $ids;
        }

        return array_values(array_filter(
            $ids,
            function (int $id) use ($actor) {
                $other = User::query()->find($id);

                return $other && $this->scope->canCollaborateWith($actor, $other);
            }
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function auditTrail(Task $task): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->where('auditable_type', Task::class)
            ->where('auditable_id', $task->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user,
                'ip_address' => $log->ip_address,
                'properties' => $log->properties,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function kanban(User $actor, array $filters = []): array
    {
        $query = Task::query()
            ->with(['assignee:id,name', 'structure:id,code,name'])
            ->visibleTo($actor)
            ->whereNull('parent_id');

        if (! empty($filters['mine'])) {
            $query->mine($actor);
        }
        if (! empty($filters['team']) && $actor->structure_id) {
            $query->where('structure_id', $actor->structure_id);
        }
        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', $filters['structure_id']);
        }
        if (! empty($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        $columns = [];
        foreach ($query->orderByDesc('created_at')->orderByDesc('id')->limit(300)->get() as $task) {
            $status = $task->status?->value ?? 'unknown';
            $columns[$status] ??= [];
            $columns[$status][] = [
                'id' => $task->id,
                'reference' => $task->reference,
                'title' => $task->title,
                'status' => $status,
                'priority' => $task->priority?->value,
                'due_at' => optional($task->due_at)?->toIso8601String(),
                'is_overdue' => $task->isOverdue(),
                'assignee' => $task->assignee,
                'progress' => $task->progress,
            ];
        }

        return $columns;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function calendar(User $actor, string $from, string $to): array
    {
        return Task::query()
            ->with(['assignee:id,name'])
            ->visibleTo($actor)
            ->whereNull('parent_id')
            ->whereNotNull('due_at')
            ->whereDate('due_at', '>=', $from)
            ->whereDate('due_at', '<=', $to)
            ->orderBy('due_at')
            ->limit(500)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'reference' => $task->reference,
                'start' => optional($task->due_at)?->toIso8601String(),
                'end' => optional($task->due_at)?->toIso8601String(),
                'status' => $task->status?->value,
                'priority' => $task->priority?->value,
                'is_overdue' => $task->isOverdue(),
                'url' => '/taches/'.$task->id,
                'assignee' => $task->assignee,
            ])
            ->all();
    }

    public function addAttachment(User $actor, Task $task, UploadedFile $file, string $kind = 'attachment'): TaskAttachment
    {
        if (! $this->access->canComment($actor, $task) && ! $this->access->canUpdate($actor, $task)) {
            abort(403);
        }

        $stored = $this->storage->store($file, 'tasks');

        $attachment = $task->attachments()->create([
            'uploaded_by' => $actor->id,
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'checksum' => $stored['checksum'],
            'kind' => $kind,
        ]);

        $this->recordHistory($task, $actor, 'attachment_added', $task->status->value, $task->status->value, $stored['original_name']);
        $this->audit->log('task.attachment_added', $task, ['actor_id' => $actor->id, 'attachment_id' => $attachment->id]);

        return $attachment->load('uploader:id,name');
    }

    /**
     * @param  list<int>  $userIds
     */
    public function syncContributors(Task $task, array $userIds): void
    {
        $sync = [];
        foreach (array_unique(array_filter($userIds)) as $userId) {
            if ((int) $userId === (int) $task->assignee_id) {
                continue;
            }
            $sync[(int) $userId] = ['role' => 'contributor'];
        }
        $task->contributors()->sync($sync);
    }

    public function recordHistory(
        Task $task,
        ?User $actor,
        string $event,
        ?string $from,
        ?string $to,
        ?string $comment = null,
        array $properties = []
    ): TaskHistory {
        return TaskHistory::query()->create([
            'task_id' => $task->id,
            'user_id' => $actor?->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'comment' => $comment,
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }

    public function transition(Task $task, TaskStatus $to): void
    {
        $this->stateMachine->assertCanTransition($task->status, $to);
        $task->status = $to;
    }

    /**
     * @return array<string, int>
     */
    public function dashboard(User $actor): array
    {
        $base = Task::query()->visibleTo($actor)->whereNull('parent_id');

        return [
            'a_faire' => (clone $base)->mine($actor)->whereIn('status', [
                TaskStatus::Imputee->value,
                TaskStatus::PriseEnCharge->value,
                TaskStatus::EnCours->value,
                TaskStatus::EnAttente->value,
                TaskStatus::Retournee->value,
            ])->count(),
            'aujourdhui' => (clone $base)->mine($actor)->whereIn('status', TaskStatus::openValues())
                ->whereDate('due_at', now()->toDateString())->count(),
            'en_retard' => (clone $base)->mine($actor)->overdue()->count(),
            'a_valider' => (clone $base)->toValidate($actor)->count(),
            'en_attente' => (clone $base)->mine($actor)->where('status', TaskStatus::EnAttente->value)->count(),
            'terminees_recentes' => (clone $base)->mine($actor)
                ->whereIn('status', [TaskStatus::Terminee->value, TaskStatus::Validee->value])
                ->where('updated_at', '>=', now()->subDays(14))->count(),
            'imputees_par_moi' => (clone $base)->assignedBy($actor)->whereIn('status', TaskStatus::openValues())->count(),
        ];
    }
}
