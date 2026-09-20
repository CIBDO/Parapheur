<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use App\Models\OlaPolicy;
use App\Models\Ticket;
use App\Models\TicketOla;
use Carbon\Carbon;

class OlaService
{
    public function __construct(
        private readonly SlaService $sla,
        private readonly TicketNotificationService $notifications,
    ) {}

    public function attachOnCreate(Ticket $ticket): ?TicketOla
    {
        if (! $ticket->support_team_id) {
            return null;
        }

        $policy = OlaPolicy::query()
            ->where('support_team_id', $ticket->support_team_id)
            ->where('is_active', true)
            ->with('calendar.exceptions')
            ->first();

        if (! $policy) {
            return null;
        }

        $calendar = $policy->calendar;
        $from = $ticket->created_at ? Carbon::parse($ticket->created_at) : now();

        $responseDue = $calendar
            ? $this->sla->addBusinessMinutes($from, (int) $policy->response_minutes, $calendar)
            : $from->copy()->addMinutes((int) $policy->response_minutes);
        $resolutionDue = $calendar
            ? $this->sla->addBusinessMinutes($from, (int) $policy->resolution_minutes, $calendar)
            : $from->copy()->addMinutes((int) $policy->resolution_minutes);

        return TicketOla::query()->create([
            'ticket_id' => $ticket->id,
            'ola_policy_id' => $policy->id,
            'response_due_at' => $responseDue,
            'resolution_due_at' => $resolutionDue,
            'paused_seconds' => 0,
        ]);
    }

    /**
     * @return array{warnings: int, breaches: int}
     */
    public function checkBreaches(): array
    {
        $warnings = 0;
        $breaches = 0;
        $now = now();

        $olas = TicketOla::query()
            ->with(['ticket', 'policy'])
            ->whereNull('paused_at')
            ->whereHas('ticket', function ($q) {
                $q->whereNotIn('status', [
                    TicketStatus::Cloture->value,
                    TicketStatus::Annule->value,
                ]);
            })
            ->get();

        foreach ($olas as $ola) {
            $ticket = $ola->ticket;
            if (! $ticket) {
                continue;
            }

            if (! $ola->response_met_at && $ola->response_due_at && $now->gte($ola->response_due_at) && ! $ola->response_breached_at) {
                $ola->update(['response_breached_at' => $now]);
                $this->notifications->notify($ticket, 'ola_breach', null, 'OLA de première réponse dépassé.');
                $breaches++;
            }

            if (! $ola->resolution_met_at && $ola->resolution_due_at && $now->gte($ola->resolution_due_at) && ! $ola->resolution_breached_at) {
                $ola->update(['resolution_breached_at' => $now]);
                $this->notifications->notify($ticket, 'ola_breach', null, 'OLA de résolution dépassé.');
                $breaches++;
            }

            $policy = $ola->policy;
            $warningPercent = (int) ($policy?->warning_percent ?? 80);
            if ($ola->warning_sent_at || ! $ola->resolution_due_at || $ola->resolution_met_at) {
                continue;
            }

            $created = $ticket->created_at ? Carbon::parse($ticket->created_at) : null;
            if (! $created) {
                continue;
            }

            $total = max(1, $created->diffInSeconds($ola->resolution_due_at));
            $elapsed = $created->diffInSeconds($now);
            $percent = (int) round(($elapsed / $total) * 100);

            if ($percent >= $warningPercent) {
                $ola->update(['warning_sent_at' => $now]);
                $this->notifications->notify($ticket, 'ola_warning', null, sprintf('(%d%%).', $percent));
                $warnings++;
            }
        }

        return compact('warnings', 'breaches');
    }
}
