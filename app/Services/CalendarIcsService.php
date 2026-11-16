<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarIcsService
{
    public function appointment(Appointment $appointment): string
    {
        $start = $appointment->start_at ? Carbon::parse($appointment->start_at) : null;
        $end = $appointment->end_at
            ? Carbon::parse($appointment->end_at)
            : ($start?->copy()->addMinutes((int) ($appointment->duration_minutes ?: 30)));

        return $this->calendar([
            $this->vevent([
                'uid' => 'appointment-'.$appointment->id.'@parapheur',
                'summary' => $appointment->subject ?: ($appointment->reference ?: 'Rendez-vous'),
                'description' => trim(($appointment->reason ?: '')."\n".($appointment->description ?: '')),
                'location' => $appointment->location ?: ($appointment->visio_url ?: ''),
                'url' => $appointment->visio_url,
                'dtstart' => $start,
                'dtend' => $end,
                'stamp' => $appointment->updated_at ?: now(),
            ]),
        ], 'Parapheur — Rendez-vous');
    }

    public function meeting(Meeting $meeting): string
    {
        $date = $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->toDateString() : now()->toDateString();
        $start = Carbon::parse($date.' '.($meeting->meeting_time ?: '09:00:00'));
        $end = $meeting->end_time
            ? Carbon::parse($date.' '.$meeting->end_time)
            : $start->copy()->addHours(2);

        return $this->calendar([
            $this->vevent([
                'uid' => 'meeting-'.$meeting->id.'@parapheur',
                'summary' => $meeting->object ?: ($meeting->title ?: $meeting->reference ?: 'Réunion'),
                'description' => (string) ($meeting->description ?: $meeting->observations ?: ''),
                'location' => $meeting->location ?: ($meeting->visio_url ?: ''),
                'url' => $meeting->visio_url,
                'dtstart' => $start,
                'dtend' => $end,
                'stamp' => $meeting->updated_at ?: now(),
            ]),
        ], 'Parapheur — Réunion');
    }

    /**
     * Feed personnel authentifié : RDV visibles + réunions où l'utilisateur est impliqué.
     */
    public function feedForUser(User $user, ?Carbon $from = null, ?Carbon $to = null): string
    {
        $from ??= now()->subDays(7)->startOfDay();
        $to ??= now()->addDays(60)->endOfDay();

        $appointments = app(AppointmentAccessService::class)
            ->visibleQuery($user)
            ->whereBetween('start_at', [$from, $to])
            ->whereNotNull('start_at')
            ->orderBy('start_at')
            ->limit(500)
            ->get();

        $meetings = Meeting::query()
            ->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()])
            ->where(function ($q) use ($user) {
                $q->where('chair_id', $user->id)
                    ->orWhere('secretary_id', $user->id)
                    ->orWhere('organizer_id', $user->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id));
            })
            ->orderBy('meeting_date')
            ->limit(500)
            ->get();

        $events = Collection::make();

        foreach ($appointments as $appointment) {
            $start = Carbon::parse($appointment->start_at);
            $end = $appointment->end_at
                ? Carbon::parse($appointment->end_at)
                : $start->copy()->addMinutes((int) ($appointment->duration_minutes ?: 30));
            $events->push($this->vevent([
                'uid' => 'appointment-'.$appointment->id.'@parapheur',
                'summary' => $appointment->subject ?: ($appointment->reference ?: 'Rendez-vous'),
                'description' => (string) ($appointment->reason ?: ''),
                'location' => $appointment->location ?: ($appointment->visio_url ?: ''),
                'url' => $appointment->visio_url,
                'dtstart' => $start,
                'dtend' => $end,
                'stamp' => $appointment->updated_at ?: now(),
            ]));
        }

        foreach ($meetings as $meeting) {
            $date = $meeting->meeting_date ? Carbon::parse($meeting->meeting_date)->toDateString() : $from->toDateString();
            $start = Carbon::parse($date.' '.($meeting->meeting_time ?: '09:00:00'));
            $end = $meeting->end_time
                ? Carbon::parse($date.' '.$meeting->end_time)
                : $start->copy()->addHours(2);
            $events->push($this->vevent([
                'uid' => 'meeting-'.$meeting->id.'@parapheur',
                'summary' => $meeting->object ?: ($meeting->title ?: $meeting->reference ?: 'Réunion'),
                'description' => (string) ($meeting->description ?: ''),
                'location' => $meeting->location ?: ($meeting->visio_url ?: ''),
                'url' => $meeting->visio_url,
                'dtstart' => $start,
                'dtend' => $end,
                'stamp' => $meeting->updated_at ?: now(),
            ]));
        }

        return $this->calendar($events->all(), 'Parapheur — Agenda personnel');
    }

    public function response(string $ics, string $filename)
    {
        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function calendar(array $events, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Parapheur DGTCP//Agenda//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
        ];

        foreach ($events as $event) {
            $lines = array_merge($lines, $event);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function vevent(array $data): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$this->escape($data['uid']),
            'DTSTAMP:'.$this->formatUtc($data['stamp'] ?? now()),
            'SUMMARY:'.$this->escape($data['summary'] ?? ''),
        ];

        if (! empty($data['dtstart'])) {
            $lines[] = 'DTSTART:'.$this->formatUtc($data['dtstart']);
        }
        if (! empty($data['dtend'])) {
            $lines[] = 'DTEND:'.$this->formatUtc($data['dtend']);
        }
        if (! empty($data['description'])) {
            $lines[] = 'DESCRIPTION:'.$this->escape($data['description']);
        }
        if (! empty($data['location'])) {
            $lines[] = 'LOCATION:'.$this->escape($data['location']);
        }
        if (! empty($data['url'])) {
            $lines[] = 'URL:'.$this->escape($data['url']);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function formatUtc(Carbon|string $value): string
    {
        $dt = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $dt->clone()->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $value): string
    {
        $value = str_replace(["\r\n", "\n", "\r"], '\\n', $value);

        return addcslashes($value, ',;\\');
    }
}
