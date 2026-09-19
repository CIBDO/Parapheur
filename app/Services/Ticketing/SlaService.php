<?php

namespace App\Services\Ticketing;

use App\Enums\TicketStatus;
use App\Models\SlaCalendar;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketSla;
use Carbon\Carbon;

class SlaService
{
    public function __construct(
        private readonly TicketNotificationService $notifications,
    ) {}

    public function attachOnCreate(Ticket $ticket): ?TicketSla
    {
        if (! $ticket->priority_id) {
            return null;
        }

        $policy = SlaPolicy::query()
            ->where('priority_id', $ticket->priority_id)
            ->where('is_active', true)
            ->with('calendar.exceptions')
            ->first();

        if (! $policy || ! $policy->calendar) {
            return null;
        }

        $from = $ticket->created_at ? Carbon::parse($ticket->created_at) : now();
        $responseDue = $this->addBusinessMinutes($from, (int) $policy->response_minutes, $policy->calendar);
        $resolutionDue = $this->addBusinessMinutes($from, (int) $policy->resolution_minutes, $policy->calendar);

        $sla = TicketSla::query()->create([
            'ticket_id' => $ticket->id,
            'sla_policy_id' => $policy->id,
            'response_due_at' => $responseDue,
            'resolution_due_at' => $resolutionDue,
            'paused_seconds' => 0,
        ]);

        $ticket->update([
            'due_response_at' => $responseDue,
            'due_resolution_at' => $resolutionDue,
        ]);

        return $sla;
    }

    public function pause(Ticket $ticket): void
    {
        $sla = $ticket->sla ?? TicketSla::query()->where('ticket_id', $ticket->id)->first();
        if (! $sla || $sla->paused_at) {
            return;
        }

        $sla->update(['paused_at' => now()]);
    }

    public function resume(Ticket $ticket): void
    {
        $sla = $ticket->sla ?? TicketSla::query()->where('ticket_id', $ticket->id)->first();
        if (! $sla || ! $sla->paused_at) {
            return;
        }

        $pausedSeconds = (int) $sla->paused_at->diffInSeconds(now());

        $responseDue = $sla->response_due_at?->copy()->addSeconds($pausedSeconds);
        $resolutionDue = $sla->resolution_due_at?->copy()->addSeconds($pausedSeconds);

        $sla->update([
            'paused_at' => null,
            'paused_seconds' => (int) $sla->paused_seconds + $pausedSeconds,
            'response_due_at' => $responseDue,
            'resolution_due_at' => $resolutionDue,
        ]);

        $ticket->update([
            'due_response_at' => $responseDue,
            'due_resolution_at' => $resolutionDue,
        ]);
    }

    public function addBusinessMinutes(Carbon $from, int $minutes, SlaCalendar $calendar): Carbon
    {
        if ($minutes <= 0) {
            return $from->copy();
        }

        if ($calendar->is_24_7) {
            return $from->copy()->addMinutes($minutes);
        }

        $cursor = $from->copy();
        $remaining = $minutes;
        $weekdays = $calendar->weekdays ?: [1, 2, 3, 4, 5];
        $startTime = (string) ($calendar->start_time ?: '08:00:00');
        $endTime = (string) ($calendar->end_time ?: '17:00:00');

        [$startH, $startM, $startS] = $this->splitTime($startTime);
        [$endH, $endM, $endS] = $this->splitTime($endTime);

        $closedDates = $calendar->relationLoaded('exceptions')
            ? $calendar->exceptions->where('is_closed', true)->map(fn ($e) => Carbon::parse($e->date)->toDateString())->all()
            : $calendar->exceptions()->where('is_closed', true)->get()
                ->map(fn ($e) => Carbon::parse($e->date)->toDateString())
                ->all();

        $guard = 0;
        while ($remaining > 0 && $guard < 20_000) {
            $guard++;
            $dow = (int) $cursor->dayOfWeekIso;
            $dateStr = $cursor->toDateString();

            if (! in_array($dow, $weekdays, true) || in_array($dateStr, $closedDates, true)) {
                $cursor->addDay()->setTime($startH, $startM, $startS);

                continue;
            }

            $dayStart = $cursor->copy()->setTime($startH, $startM, $startS);
            $dayEnd = $cursor->copy()->setTime($endH, $endM, $endS);

            if ($cursor->lt($dayStart)) {
                $cursor = $dayStart->copy();
            }

            if ($cursor->gte($dayEnd)) {
                $cursor->addDay()->setTime($startH, $startM, $startS);

                continue;
            }

            $available = (int) $cursor->diffInMinutes($dayEnd);
            if ($available <= 0) {
                $cursor->addDay()->setTime($startH, $startM, $startS);

                continue;
            }

            if ($remaining <= $available) {
                return $cursor->copy()->addMinutes($remaining);
            }

            $remaining -= $available;
            $cursor->addDay()->setTime($startH, $startM, $startS);
        }

        return $cursor;
    }

    /**
     * Marque les warnings / breaches et notifie.
     *
     * @return array{warnings: int, breaches: int}
     */
    public function checkBreaches(): array
    {
        $warnings = 0;
        $breaches = 0;
        $now = now();

        $slas = TicketSla::query()
            ->with(['ticket.priority', 'policy'])
            ->whereNull('paused_at')
            ->whereHas('ticket', function ($q) {
                $q->whereNotIn('status', [
                    TicketStatus::Cloture->value,
                    TicketStatus::Annule->value,
                ]);
            })
            ->get();

        foreach ($slas as $sla) {
            $ticket = $sla->ticket;
            if (! $ticket) {
                continue;
            }

            $policy = $sla->policy;
            $warningPercent = (int) ($policy?->warning_percent ?? 80);

            if (! $sla->response_met_at && $sla->response_due_at && $now->gte($sla->response_due_at) && ! $sla->response_breached_at) {
                $sla->update(['response_breached_at' => $now]);
                $this->notifications->notify($ticket, 'sla_breach', null, 'SLA de première réponse dépassé.', [
                    'kind' => 'response',
                ]);
                $breaches++;
            }

            if (! $sla->resolution_met_at && $sla->resolution_due_at && $now->gte($sla->resolution_due_at) && ! $sla->resolution_breached_at) {
                $sla->update(['resolution_breached_at' => $now]);
                $this->notifications->notify($ticket, 'sla_breach', null, 'SLA de résolution dépassé.', [
                    'kind' => 'resolution',
                ]);
                $breaches++;
            }

            if ($sla->warning_sent_at || ! $sla->resolution_due_at || $sla->resolution_met_at) {
                continue;
            }

            $created = $ticket->created_at ? Carbon::parse($ticket->created_at) : null;
            if (! $created) {
                continue;
            }

            $total = max(1, $created->diffInSeconds($sla->resolution_due_at));
            $elapsed = $created->diffInSeconds($now);
            $percent = (int) round(($elapsed / $total) * 100);

            if ($percent >= $warningPercent) {
                $sla->update(['warning_sent_at' => $now]);
                $this->notifications->notify(
                    $ticket,
                    'sla_warning',
                    null,
                    sprintf('SLA proche du dépassement (%d%%).', $percent),
                    ['percent' => $percent]
                );
                $warnings++;
            }
        }

        return compact('warnings', 'breaches');
    }

    public function onStatusChange(Ticket $ticket, TicketStatus $old, TicketStatus $new): void
    {
        if ($new->pausesSla() && ! $old->pausesSla()) {
            $this->pause($ticket);
        } elseif ($old->pausesSla() && ! $new->pausesSla()) {
            $this->resume($ticket);
        }

        $sla = $ticket->sla ?? TicketSla::query()->where('ticket_id', $ticket->id)->first();
        if (! $sla) {
            return;
        }

        $takenStatuses = [
            TicketStatus::PrisEnCharge->value,
            TicketStatus::EnCours->value,
        ];

        if (in_array($new->value, $takenStatuses, true) && ! $sla->response_met_at) {
            $sla->update(['response_met_at' => now()]);
        }

        if ($new === TicketStatus::Resolu && ! $sla->resolution_met_at) {
            $sla->update(['resolution_met_at' => now()]);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function splitTime(string $time): array
    {
        $parts = array_pad(explode(':', $time), 3, '0');

        return [(int) $parts[0], (int) $parts[1], (int) $parts[2]];
    }
}
