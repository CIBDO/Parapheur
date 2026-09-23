<?php

namespace App\Services\Tasks;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\InstructionStatus;
use App\Enums\NumberingSequenceCode;
use App\Enums\TaskSource;
use App\Models\Instruction;
use App\Models\InstructionUpdate;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NumberingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InstructionService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly AuditLogger $audit,
        private readonly TaskService $tasks,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Instruction::query()->with(['assignee:id,name', 'issuer:id,name', 'document:id,reference', 'structure:id,code,name']);

        if (! $actor->can('instruction.view') && ! $actor->can('instructions.manage') && ! $actor->can('admin.access') && ! $actor->can('task.view_all')) {
            $query->where(function ($q) use ($actor) {
                $q->where('assignee_id', $actor->id)->orWhere('issuer_id', $actor->id);
            });
        }

        if (! empty($filters['mine'])) {
            $query->where('assignee_id', $actor->id);
        }
        if (! empty($filters['issued_by_me'])) {
            $query->where('issuer_id', $actor->id);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['overdue'])) {
            $query->whereIn('status', InstructionStatus::openValues())
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now());
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('reference', 'like', $q)
                    ->orWhere('title', 'like', $q)
                    ->orWhere('body', 'like', $q);
            });
        }

        return $query->orderByDesc('created_at')->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Instruction
    {
        return DB::transaction(function () use ($actor, $data) {
            $instruction = Instruction::query()->create([
                'reference' => $this->numbering->nextNumber(NumberingSequenceCode::Instruction),
                'document_id' => $data['document_id'] ?? null,
                'meeting_decision_id' => $data['meeting_decision_id'] ?? null,
                'issuer_id' => $actor->id,
                'assignee_id' => $data['assignee_id'],
                'structure_id' => $data['structure_id'] ?? null,
                'title' => $data['title'],
                'body' => $data['body'] ?? $data['title'],
                'priority' => $data['priority'] ?? DocumentPriority::Normale->value,
                'confidentiality' => $data['confidentiality'] ?? DocumentConfidentiality::Normal->value,
                'source_kind' => $data['source_kind'] ?? TaskSource::Manual->value,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => $data['status'] ?? InstructionStatus::AFaire->value,
                'due_date' => $data['due_date'] ?? null,
                'is_personal' => (bool) ($data['is_personal'] ?? false),
            ]);

            if (! empty($data['create_execution_task'])) {
                $this->tasks->create($actor, [
                    'title' => $instruction->title,
                    'description' => $instruction->body,
                    'assignee_id' => $instruction->assignee_id,
                    'structure_id' => $instruction->structure_id,
                    'priority' => $instruction->priority?->value ?? DocumentPriority::Normale->value,
                    'due_at' => $instruction->due_date?->endOfDay(),
                    'instruction_id' => $instruction->id,
                    'source_kind' => TaskSource::Instruction->value,
                    'source_type' => Instruction::class,
                    'source_id' => $instruction->id,
                    'validator_id' => $actor->id,
                ]);
            }

            $this->audit->log('instruction.created', $instruction, ['actor_id' => $actor->id]);

            return $instruction->fresh(['assignee', 'issuer', 'tasks']);
        });
    }

    /**
     * Bridge helper used by existing modules (courrier, réunions, etc.).
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromLegacy(User $actor, array $data): Instruction
    {
        $data['create_execution_task'] = $data['create_execution_task'] ?? true;

        return $this->create($actor, $data);
    }

    public function updateStatus(User $actor, Instruction $instruction, string $status, ?string $body = null): Instruction
    {
        $allowed = array_map(fn (InstructionStatus $s) => $s->value, InstructionStatus::cases());
        if (! in_array($status, $allowed, true)) {
            abort(422, 'Statut instruction invalide.');
        }

        return DB::transaction(function () use ($actor, $instruction, $status, $body) {
            $instruction->status = $status;
            if ($status === InstructionStatus::EnCours->value) {
                $instruction->started_at = $instruction->started_at ?? now();
            }
            if ($status === InstructionStatus::Executee->value) {
                $instruction->completed_at = now();
            }
            if ($status === InstructionStatus::Cloturee->value) {
                $instruction->closed_at = now();
            }
            if ($status === InstructionStatus::Annulee->value) {
                $instruction->cancelled_at = now();
                $instruction->cancel_reason = $body;
            }
            $instruction->save();

            if ($instruction->meeting_decision_id && $status === InstructionStatus::Executee->value) {
                $instruction->meetingDecision?->update([
                    'status' => 'executee',
                    'executed_at' => now(),
                    'execution_declared_by' => $actor->id,
                ]);
            }
            if ($instruction->meeting_decision_id && $status === InstructionStatus::Cloturee->value) {
                $instruction->meetingDecision?->update([
                    'status' => 'cloturee',
                    'execution_validated_by' => $actor->id,
                    'execution_validated_at' => now(),
                ]);
            }

            if ($body) {
                InstructionUpdate::query()->create([
                    'instruction_id' => $instruction->id,
                    'user_id' => $actor->id,
                    'status' => $status,
                    'body' => $body,
                ]);
            }

            $this->audit->log('instruction.status_updated', $instruction, [
                'actor_id' => $actor->id,
                'status' => $status,
            ]);

            return $instruction->fresh(['updates.user', 'assignee', 'tasks']);
        });
    }

    /**
     * @param  array<string, mixed>  $taskData
     */
    public function addExecutionTask(User $actor, Instruction $instruction, array $taskData): Task
    {
        return $this->tasks->create($actor, array_merge($taskData, [
            'instruction_id' => $instruction->id,
            'source_kind' => TaskSource::Instruction->value,
            'source_type' => Instruction::class,
            'source_id' => $instruction->id,
            'validator_id' => $taskData['validator_id'] ?? $instruction->issuer_id,
        ]));
    }
}
