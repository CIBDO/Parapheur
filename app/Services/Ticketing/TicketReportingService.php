<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use App\Models\SupportTeamMember;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketSatisfaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketReportingService
{
    /**
     * @return array<string, int|float>
     */
    public function dashboardRequester(User $user): array
    {
        $base = Ticket::query()->where('requester_id', $user->id);

        return [
            'open' => (clone $base)->whereNotIn('status', [
                TicketStatus::Cloture->value,
                TicketStatus::Annule->value,
            ])->count(),
            'in_progress' => (clone $base)->whereIn('status', [
                TicketStatus::PrisEnCharge->value,
                TicketStatus::EnCours->value,
                TicketStatus::Affecte->value,
            ])->count(),
            'waiting_on_me' => (clone $base)->where('status', TicketStatus::EnAttenteDemandeur->value)->count(),
            'resolved_recent' => (clone $base)
                ->where('status', TicketStatus::Resolu->value)
                ->where('resolved_at', '>=', now()->subDays(14))
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function dashboardAgent(User $user): array
    {
        $assigned = Ticket::query()->where('assignee_id', $user->id);

        return [
            'assigned' => (clone $assigned)->whereNotIn('status', [
                TicketStatus::Cloture->value,
                TicketStatus::Annule->value,
            ])->count(),
            'not_taken' => (clone $assigned)->where('status', TicketStatus::Affecte->value)->count(),
            'sla_warning' => (clone $assigned)->whereHas('sla', fn ($q) => $q->whereNotNull('warning_sent_at')->whereNull('resolution_breached_at'))->count(),
            'sla_breached' => (clone $assigned)->whereHas('sla', function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('response_breached_at')
                        ->orWhereNotNull('resolution_breached_at');
                });
            })->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardTeam(User $user, ?int $teamId = null): array
    {
        $teamIds = $teamId
            ? [$teamId]
            : SupportTeamMember::query()->where('user_id', $user->id)->pluck('support_team_id')->all();

        $base = Ticket::query()->whereIn('support_team_id', $teamIds ?: [0]);

        $byAgent = (clone $base)
            ->whereNotNull('assignee_id')
            ->whereNotIn('status', [TicketStatus::Cloture->value, TicketStatus::Annule->value])
            ->select('assignee_id', DB::raw('count(*) as total'))
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id')
            ->all();

        return [
            'team_open' => (clone $base)->whereNotIn('status', [
                TicketStatus::Cloture->value,
                TicketStatus::Annule->value,
            ])->count(),
            'unassigned' => (clone $base)->whereNull('assignee_id')->whereNotIn('status', [
                TicketStatus::Cloture->value,
                TicketStatus::Annule->value,
            ])->count(),
            'sla_breached' => (clone $base)->whereHas('sla', function ($q) {
                $q->whereNotNull('response_breached_at')->orWhereNotNull('resolution_breached_at');
            })->count(),
            'load_by_agent' => $byAgent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardManagement(): array
    {
        $openStatuses = array_diff(TicketStatus::values(), [
            TicketStatus::Cloture->value,
            TicketStatus::Annule->value,
        ]);

        $volume = Ticket::query()->where('created_at', '>=', now()->subDays(30))->count();

        $withSla = Ticket::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->whereHas('sla')
            ->with('sla')
            ->get();

        $met = $withSla->filter(fn (Ticket $t) => $t->sla && ! $t->sla->resolution_breached_at)->count();
        $slaPercent = $withSla->count() > 0 ? round(($met / $withSla->count()) * 100, 1) : null;

        $avgResolutionHours = Ticket::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_h'))
            ->value('avg_h');

        $avgSatisfaction = TicketSatisfaction::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('score');

        $topCategories = Ticket::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('ticket_category_id')
            ->select('ticket_category_id', DB::raw('count(*) as total'))
            ->groupBy('ticket_category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'ticket_category_id')
            ->all();

        return [
            'volume_30d' => $volume,
            'open' => Ticket::query()->whereIn('status', $openStatuses)->count(),
            'sla_met_percent_30d' => $slaPercent,
            'avg_resolution_hours_30d' => $avgResolutionHours !== null ? round((float) $avgResolutionHours, 1) : null,
            'avg_satisfaction_30d' => $avgSatisfaction !== null ? round((float) $avgSatisfaction, 2) : null,
            'top_categories' => $topCategories,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reportVolume(?string $from = null, ?string $to = null): array
    {
        $query = Ticket::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->orderBy('day');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function reportSla(?string $from = null, ?string $to = null): array
    {
        $query = Ticket::query()->whereHas('sla')->with('sla');
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $tickets = $query->get();
        $breached = $tickets->filter(fn (Ticket $t) => $t->sla?->resolution_breached_at || $t->sla?->response_breached_at)->count();

        return [
            'total_with_sla' => $tickets->count(),
            'breached' => $breached,
            'met' => $tickets->count() - $breached,
            'met_percent' => $tickets->count() > 0
                ? round((($tickets->count() - $breached) / $tickets->count()) * 100, 1)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reportSatisfaction(?string $from = null, ?string $to = null): array
    {
        $query = TicketSatisfaction::query();
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return [
            'count' => (clone $query)->count(),
            'average' => ($avg = (clone $query)->avg('score')) !== null ? round((float) $avg, 2) : null,
            'distribution' => (clone $query)
                ->select('score', DB::raw('count(*) as total'))
                ->groupBy('score')
                ->orderBy('score')
                ->pluck('total', 'score')
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reportByCategory(?string $from = null, ?string $to = null): array
    {
        $query = Ticket::query()
            ->select('ticket_category_id', DB::raw('count(*) as total'))
            ->groupBy('ticket_category_id')
            ->orderByDesc('total');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->map(fn ($r) => [
            'ticket_category_id' => $r->ticket_category_id,
            'category' => $r->ticket_category_id
                ? TicketCategory::query()->whereKey($r->ticket_category_id)->value('name')
                : 'Sans catégorie',
            'total' => (int) $r->total,
        ])->all();
    }
}
