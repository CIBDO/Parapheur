<?php

namespace App\Services\Ticketing;

use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketTaskService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<TicketTask>
     */
    public function list(Ticket $ticket, bool $includePrivate = true): array
    {
        $q = $ticket->tasks()->with(['creator:id,name', 'assignee:id,name']);
        if (! $includePrivate) {
            $q->where('is_private', false);
        }

        return $q->get()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Ticket $ticket, User $actor, array $data): TicketTask
    {
        return DB::transaction(function () use ($ticket, $actor, $data) {
            $task = TicketTask::query()->create([
                'ticket_id' => $ticket->id,
                'created_by' => $actor->id,
                'assignee_id' => $data['assignee_id'] ?? null,
                'instruction_id' => $data['instruction_id'] ?? null,
                'title' => $data['title'],
                'content' => $data['content'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'category' => $data['category'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'is_private' => (bool) ($data['is_private'] ?? false),
                'planned_start_at' => $data['planned_start_at'] ?? null,
                'planned_end_at' => $data['planned_end_at'] ?? null,
            ]);

            $this->audit->log('ticket.task_created', $ticket, [
                'actor_id' => $actor->id,
                'task_id' => $task->id,
            ]);

            return $task->fresh(['creator', 'assignee']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, TicketTask $task, User $actor, array $data): TicketTask
    {
        if ((int) $task->ticket_id !== (int) $ticket->id) {
            throw new InvalidArgumentException('Tâche hors ticket.');
        }

        return DB::transaction(function () use ($ticket, $task, $actor, $data) {
            $allowed = [
                'title', 'content', 'assignee_id', 'instruction_id', 'category',
                'duration_minutes', 'is_private', 'planned_start_at', 'planned_end_at', 'status',
            ];
            $payload = array_intersect_key($data, array_flip($allowed));

            if (isset($payload['status'])) {
                $payload = array_merge($payload, $this->statusTimestamps((string) $payload['status'], $task));
            }

            $task->update($payload);

            $this->audit->log('ticket.task_updated', $ticket, [
                'actor_id' => $actor->id,
                'task_id' => $task->id,
            ]);

            return $task->fresh(['creator', 'assignee']);
        });
    }

    public function delete(Ticket $ticket, TicketTask $task, User $actor): void
    {
        if ((int) $task->ticket_id !== (int) $ticket->id) {
            throw new InvalidArgumentException('Tâche hors ticket.');
        }

        $id = $task->id;
        $task->delete();

        $this->audit->log('ticket.task_deleted', $ticket, [
            'actor_id' => $actor->id,
            'task_id' => $id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusTimestamps(string $status, TicketTask $task): array
    {
        return match ($status) {
            'doing' => [
                'started_at' => $task->started_at ?? now(),
                'completed_at' => null,
            ],
            'done' => [
                'started_at' => $task->started_at ?? now(),
                'completed_at' => now(),
            ],
            default => [
                'started_at' => null,
                'completed_at' => null,
            ],
        };
    }
}
