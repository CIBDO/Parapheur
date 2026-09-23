<?php

namespace App\Services\Tasks;

use App\Enums\InstructionStatus;
use App\Enums\TaskStatus;
use App\Models\Instruction;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskReportService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(User $actor, int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();
        $base = Task::query()->visibleTo($actor)->whereNull('parent_id');

        $created = (clone $base)->where('created_at', '>=', $from)->count();
        $validated = (clone $base)->where('status', TaskStatus::Validee->value)
            ->where('validated_at', '>=', $from)->count();
        $overdue = (clone $base)->overdue()->count();
        $open = (clone $base)->whereIn('status', TaskStatus::openValues())->count();
        $returned = (clone $base)->where('status', TaskStatus::Retournee->value)->count();

        $avgCompletionHours = null;
        $completed = (clone $base)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->get(['created_at', 'completed_at']);

        if ($completed->isNotEmpty()) {
            $avgCompletionHours = round(
                $completed->avg(fn ($t) => $t->created_at->diffInMinutes($t->completed_at) / 60),
                1
            );
        }
        $instructionsOpen = Instruction::query()
            ->whereIn('status', InstructionStatus::openValues())
            ->count();
        $instructionsDone = Instruction::query()
            ->whereIn('status', [InstructionStatus::Executee->value, InstructionStatus::Cloturee->value])
            ->where('updated_at', '>=', $from)
            ->count();

        return [
            'period_days' => $days,
            'volume_created' => $created,
            'volume_validated' => $validated,
            'open' => $open,
            'overdue' => $overdue,
            'returned' => $returned,
            'completion_rate_percent' => $created > 0 ? round(($validated / $created) * 100, 1) : null,
            'overdue_rate_percent' => $open > 0 ? round(($overdue / max($open, 1)) * 100, 1) : 0,
            'avg_completion_hours' => $avgCompletionHours,
            'instructions_open' => $instructionsOpen,
            'instructions_executed' => $instructionsDone,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byStructure(User $actor, int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();

        return Task::query()
            ->visibleTo($actor)
            ->whereNull('parent_id')
            ->where('created_at', '>=', $from)
            ->leftJoin('structures', 'structures.id', '=', 'tasks.structure_id')
            ->select([
                'tasks.structure_id',
                DB::raw('COALESCE(structures.code, \'N/A\') as structure_code'),
                DB::raw('COALESCE(structures.name, \'Sans structure\') as structure_name'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN tasks.status = \'validee\' THEN 1 ELSE 0 END) as validated'),
                DB::raw('SUM(CASE WHEN tasks.due_at < NOW() AND tasks.status IN (\''.implode("','", TaskStatus::openValues()).'\') THEN 1 ELSE 0 END) as overdue'),
            ])
            ->groupBy('tasks.structure_id', 'structures.code', 'structures.name')
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'structure_id' => $row->structure_id,
                'structure_code' => $row->structure_code,
                'structure_name' => $row->structure_name,
                'total' => (int) $row->total,
                'validated' => (int) $row->validated,
                'overdue' => (int) $row->overdue,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byPriority(User $actor): array
    {
        return Task::query()
            ->visibleTo($actor)
            ->whereNull('parent_id')
            ->whereIn('status', TaskStatus::openValues())
            ->select('priority', DB::raw('COUNT(*) as total'))
            ->groupBy('priority')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'priority' => $row->priority?->value ?? $row->getRawOriginal('priority'),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bySource(User $actor, int $days = 30): array
    {
        $from = now()->subDays($days)->startOfDay();

        return Task::query()
            ->visibleTo($actor)
            ->whereNull('parent_id')
            ->where('created_at', '>=', $from)
            ->select('source_kind', DB::raw('COUNT(*) as total'))
            ->groupBy('source_kind')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'source_kind' => $row->source_kind?->value ?? $row->getRawOriginal('source_kind'),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function workloadByAssignee(User $actor, int $limit = 20): array
    {
        return Task::query()
            ->visibleTo($actor)
            ->whereNull('parent_id')
            ->whereIn('status', TaskStatus::openValues())
            ->whereNotNull('assignee_id')
            ->leftJoin('users', 'users.id', '=', 'tasks.assignee_id')
            ->select([
                'tasks.assignee_id',
                'users.name as assignee_name',
                DB::raw('COUNT(*) as open_total'),
                DB::raw('SUM(CASE WHEN tasks.due_at < NOW() THEN 1 ELSE 0 END) as overdue'),
            ])
            ->groupBy('tasks.assignee_id', 'users.name')
            ->orderByDesc('open_total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'assignee_id' => $row->assignee_id,
                'assignee_name' => $row->assignee_name,
                'open_total' => (int) $row->open_total,
                'overdue' => (int) $row->overdue,
            ])
            ->all();
    }
}
