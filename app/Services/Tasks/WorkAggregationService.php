<?php

namespace App\Services\Tasks;

use App\Enums\InstructionStatus;
use App\Enums\TaskStatus;
use App\Models\Instruction;
use App\Models\Task;
use App\Models\User;

class WorkAggregationService
{
    /**
     * @return array{
     *   counts: array<string, int>,
     *   tasks: list<array<string, mixed>>,
     *   instructions: list<array<string, mixed>>,
     *   other: list<array<string, mixed>>
     * }
     */
    public function forUser(User $user, int $limit = 10): array
    {
        $tasksBase = Task::query()->visibleTo($user)->whereNull('parent_id');

        $myOpen = (clone $tasksBase)->mine($user)->whereIn('status', [
            TaskStatus::Imputee->value,
            TaskStatus::PriseEnCharge->value,
            TaskStatus::EnCours->value,
            TaskStatus::EnAttente->value,
            TaskStatus::Retournee->value,
            TaskStatus::Terminee->value,
            TaskStatus::AValider->value,
        ]);

        $counts = [
            'a_faire' => (clone $myOpen)->whereIn('status', [
                TaskStatus::Imputee->value,
                TaskStatus::PriseEnCharge->value,
                TaskStatus::EnCours->value,
                TaskStatus::Retournee->value,
            ])->count(),
            'a_valider' => Task::query()->toValidate($user)->whereNull('parent_id')->count(),
            'en_retard' => (clone $tasksBase)->mine($user)->overdue()->count(),
            'aujourdhui' => (clone $myOpen)->whereDate('due_at', now()->toDateString())->count(),
            'en_attente' => (clone $myOpen)->where('status', TaskStatus::EnAttente->value)->count(),
            'a_venir' => (clone $myOpen)->whereDate('due_at', '>', now()->toDateString())->count(),
            'instructions' => Instruction::query()
                ->where('assignee_id', $user->id)
                ->whereIn('status', InstructionStatus::openValues())
                ->count(),
        ];

        $taskItems = (clone $myOpen)
            ->with(['assignee:id,name'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit($limit)
            ->get()
            ->map(fn (Task $t) => $this->mapTask($t))
            ->all();

        $instructionItems = Instruction::query()
            ->where('assignee_id', $user->id)
            ->whereIn('status', InstructionStatus::openValues())
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(fn (Instruction $i) => $this->mapInstruction($i))
            ->all();

        return [
            'counts' => $counts,
            'tasks' => $taskItems,
            'instructions' => $instructionItems,
            'other' => $this->otherActions($user),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function otherActions(User $user): array
    {
        $items = [];

        // Lightweight counts from related modules when tables exist — avoid hard failures
        try {
            if (class_exists(\App\Models\CorrespondenceAssignment::class)) {
                $mailCount = \App\Models\CorrespondenceAssignment::query()
                    ->where('assignee_id', $user->id)
                    ->whereIn('status', ['imputed', 'in_progress', 'taken_charge', 'a_traiter', 'en_cours'])
                    ->count();
                if ($mailCount > 0) {
                    $items[] = [
                        'type' => 'courrier',
                        'label' => $mailCount.' courrier(s) à traiter',
                        'count' => $mailCount,
                        'url' => '/courrier',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        try {
            if (class_exists(\App\Models\Ticket::class)) {
                $ticketCount = \App\Models\Ticket::query()
                    ->where(function ($q) use ($user) {
                        $q->where('assignee_id', $user->id)->orWhere('requester_id', $user->id);
                    })
                    ->whereNotIn('status', ['cloture', 'annule', 'resolu'])
                    ->count();
                if ($ticketCount > 0) {
                    $items[] = [
                        'type' => 'ticket',
                        'label' => $ticketCount.' ticket(s) actif(s)',
                        'count' => $ticketCount,
                        'url' => '/ticketing',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        try {
            if (class_exists(\App\Models\Document::class)) {
                $docs = \App\Models\Document::query()
                    ->where(function ($q) use ($user) {
                        $q->where('current_assignee_id', $user->id)
                            ->orWhere('created_by', $user->id);
                    })
                    ->whereIn('status', ['en_circuit', 'a_viser', 'a_valider', 'en_attente'])
                    ->count();
                if ($docs > 0) {
                    $items[] = [
                        'type' => 'parapheur',
                        'label' => $docs.' document(s) à traiter',
                        'count' => $docs,
                        'url' => '/parapheur',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        try {
            if (class_exists(\App\Models\Meeting::class)) {
                $meetings = \App\Models\Meeting::query()
                    ->whereDate('meeting_date', '>=', now()->toDateString())
                    ->whereDate('meeting_date', '<=', now()->addDays(7)->toDateString())
                    ->where(function ($q) use ($user) {
                        $q->where('chair_id', $user->id)
                            ->orWhere('secretary_id', $user->id)
                            ->orWhere('organizer_id', $user->id)
                            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id));
                    })
                    ->count();
                if ($meetings > 0) {
                    $items[] = [
                        'type' => 'meeting',
                        'label' => $meetings.' réunion(s) à venir',
                        'count' => $meetings,
                        'url' => '/parapheur/reunions',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        try {
            if (class_exists(\App\Models\Appointment::class)) {
                $rdv = \App\Models\Appointment::query()
                    ->whereDate('starts_at', '>=', now()->toDateString())
                    ->whereDate('starts_at', '<=', now()->addDays(3)->toDateString())
                    ->where(function ($q) use ($user) {
                        $q->where('organizer_id', $user->id)
                            ->orWhere('host_id', $user->id)
                            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id));
                    })
                    ->count();
                if ($rdv > 0) {
                    $items[] = [
                        'type' => 'appointment',
                        'label' => $rdv.' rendez-vous à venir',
                        'count' => $rdv,
                        'url' => '/parapheur/agenda',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTask(Task $task): array
    {
        return [
            'type' => 'task',
            'id' => $task->id,
            'reference' => $task->reference,
            'title' => $task->title,
            'status' => $task->status?->value,
            'status_label' => $task->status?->label(),
            'priority' => $task->priority?->value,
            'due_at' => optional($task->due_at)?->toIso8601String(),
            'due_bucket' => $task->dueBucket(),
            'is_overdue' => $task->isOverdue(),
            'url' => '/taches/'.$task->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInstruction(Instruction $instruction): array
    {
        return [
            'type' => 'instruction',
            'id' => $instruction->id,
            'reference' => $instruction->reference,
            'title' => $instruction->title,
            'status' => $instruction->status?->value ?? $instruction->getRawOriginal('status'),
            'priority' => $instruction->priority?->value,
            'due_at' => optional($instruction->due_date)?->toDateString(),
            'is_overdue' => $instruction->isOverdue(),
            'url' => '/taches/instructions/'.$instruction->id,
        ];
    }
}
