<?php

namespace App\Services\Tasks;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\NumberingSequenceCode;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
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
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }
        if (! empty($filters['structure_id'])) {
            $query->where('structure_id', $filters['structure_id']);
        }
        if (! empty($filters['instruction_id'])) {
            $query->where('instruction_id', $filters['instruction_id']);
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
                    ->orWhere('description', 'like', $q);
            });
        }
        if (! empty($filters['due_from'])) {
            $query->whereDate('due_at', '>=', $filters['due_from']);
        }
        if (! empty($filters['due_to'])) {
            $query->whereDate('due_at', '<=', $filters['due_to']);
        }

        $sort = $filters['sort'] ?? 'due_at';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        if ($sort === 'due_at') {
            $query->orderByRaw('due_at is null')->orderBy('due_at', $dir)->orderByDesc('id');
        } else {
            $query->orderBy($sort, $dir);
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
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Task
    {
        return DB::transaction(function () use ($actor, $data) {
            $asDraft = (bool) ($data['as_draft'] ?? false);
            $assigneeId = $data['assignee_id'] ?? null;
            $status = $asDraft || ! $assigneeId
                ? TaskStatus::Brouillon
                : TaskStatus::Imputee;

            $task = Task::query()->create([
                'reference' => $this->numbering->nextNumber(NumberingSequenceCode::Task),
                'parent_id' => $data['parent_id'] ?? null,
                'instruction_id' => $data['instruction_id'] ?? null,
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
            $this->recordHistory($task, $actor, 'created', null, $status->value, 'Création de la tâche');
            $this->audit->log('task.created', $task, ['actor_id' => $actor->id]);

            if ($status === TaskStatus::Imputee && $task->assignee_id) {
                $this->notifications->notifyAssigned($task);
            }

            $this->reminders->scheduleFor($task);

            return $task->fresh(['assignee', 'creator', 'contributors']);
        });
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
            throw new InvalidArgumentException('Profondeur de sous-tâches limitée à un niveau.');
        }

        $data['parent_id'] = $parent->id;
        $data['instruction_id'] = $data['instruction_id'] ?? $parent->instruction_id;
        $data['source_kind'] = $data['source_kind'] ?? TaskSource::Manual->value;

        return $this->create($actor, $data);
    }

    public function addComment(User $actor, Task $task, string $body): TaskComment
    {
        if (! $this->access->canComment($actor, $task)) {
            abort(403);
        }

        $comment = $task->comments()->create([
            'user_id' => $actor->id,
            'body' => $body,
        ]);

        $this->recordHistory($task, $actor, 'commented', $task->status->value, $task->status->value, $body);
        $this->notifications->notifyComment($task, $actor, $body);
        $this->audit->log('task.commented', $task, ['actor_id' => $actor->id, 'comment_id' => $comment->id]);

        return $comment->load('user:id,name');
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
