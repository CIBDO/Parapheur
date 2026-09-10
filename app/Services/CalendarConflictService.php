<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\MeetingStatus;
use App\Models\Appointment;
use App\Models\CalendarUnavailability;
use App\Models\Meeting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CalendarConflictService
{
    /**
     * @return list<array{source: string, id: int, title: string, start_at: string, end_at: string, message: string}>
     */
    public function detectConflicts(
        int $directorId,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?int $ignoreAppointmentId = null,
        ?int $ignoreMeetingId = null,
        ?int $ignoreUnavailabilityId = null,
    ): array {
        $conflicts = [];

        $appointments = Appointment::query()
            ->where('director_id', $directorId)
            ->where('blocks_calendar', true)
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->whereNotIn('status', [
                AppointmentStatus::Refuse->value,
                AppointmentStatus::Annule->value,
                AppointmentStatus::Archive->value,
                AppointmentStatus::Brouillon->value,
            ])
            ->get();

        foreach ($appointments as $appointment) {
            $conflicts[] = [
                'source' => 'appointment',
                'id' => $appointment->id,
                'title' => $appointment->displaySubject(),
                'start_at' => $appointment->start_at->toIso8601String(),
                'end_at' => $appointment->end_at->toIso8601String(),
                'message' => sprintf(
                    'Le Directeur a déjà un rendez-vous de %s à %s (%s).',
                    $appointment->start_at->format('H:i'),
                    $appointment->end_at->format('H:i'),
                    $appointment->displaySubject()
                ),
            ];
        }

        $meetings = Meeting::query()
            ->where('chair_id', $directorId)
            ->whereNotNull('meeting_date')
            ->when($ignoreMeetingId, fn ($q) => $q->where('id', '!=', $ignoreMeetingId))
            ->whereNotIn('status', [
                MeetingStatus::Annulee->value,
                MeetingStatus::Archivee->value,
                MeetingStatus::Brouillon->value,
            ])
            ->get()
            ->filter(function (Meeting $meeting) use ($startAt, $endAt) {
                [$mStart, $mEnd] = $this->meetingWindow($meeting);
                if (! $mStart || ! $mEnd) {
                    return false;
                }

                return $mStart->lt($endAt) && $mEnd->gt($startAt);
            });

        foreach ($meetings as $meeting) {
            [$mStart, $mEnd] = $this->meetingWindow($meeting);
            $conflicts[] = [
                'source' => 'meeting',
                'id' => $meeting->id,
                'title' => $meeting->displayTitle(),
                'start_at' => $mStart->toIso8601String(),
                'end_at' => $mEnd->toIso8601String(),
                'message' => sprintf(
                    'Le Directeur a déjà une réunion de %s à %s (%s).',
                    $mStart->format('H:i'),
                    $mEnd->format('H:i'),
                    $meeting->displayTitle()
                ),
            ];
        }

        $unavailabilities = CalendarUnavailability::query()
            ->where('user_id', $directorId)
            ->where('is_active', true)
            ->where('blocks_calendar', true)
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->when($ignoreUnavailabilityId, fn ($q) => $q->where('id', '!=', $ignoreUnavailabilityId))
            ->get();

        foreach ($unavailabilities as $slot) {
            $conflicts[] = [
                'source' => 'unavailability',
                'id' => $slot->id,
                'title' => $slot->title,
                'start_at' => $slot->start_at->toIso8601String(),
                'end_at' => $slot->end_at->toIso8601String(),
                'message' => sprintf(
                    'Le Directeur est indisponible de %s à %s (%s).',
                    $slot->start_at->format('H:i'),
                    $slot->end_at->format('H:i'),
                    $slot->kind?->label() ?? $slot->title
                ),
            ];
        }

        return array_values($conflicts);
    }

    /**
     * @return array{before: ?array{start_at: string, end_at: string}, after: ?array{start_at: string, end_at: string}}
     */
    public function suggestAlternatives(
        int $directorId,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?int $ignoreAppointmentId = null,
    ): array {
        $duration = $startAt->diffInMinutes($endAt);
        if ($duration <= 0) {
            $duration = 30;
        }

        $beforeStart = $startAt->copy()->subMinutes($duration);
        $beforeEnd = $startAt->copy();
        $afterStart = $endAt->copy();
        $afterEnd = $endAt->copy()->addMinutes($duration);

        $beforeOk = empty($this->detectConflicts($directorId, $beforeStart, $beforeEnd, $ignoreAppointmentId));
        $afterOk = empty($this->detectConflicts($directorId, $afterStart, $afterEnd, $ignoreAppointmentId));

        return [
            'before' => $beforeOk ? [
                'start_at' => $beforeStart->toIso8601String(),
                'end_at' => $beforeEnd->toIso8601String(),
            ] : null,
            'after' => $afterOk ? [
                'start_at' => $afterStart->toIso8601String(),
                'end_at' => $afterEnd->toIso8601String(),
            ] : null,
        ];
    }

    public function isOutsideWorkingHours(CarbonInterface $startAt, CarbonInterface $endAt): bool
    {
        $morningStart = $startAt->copy()->setTime(8, 0);
        $morningEnd = $startAt->copy()->setTime(12, 30);
        $afternoonStart = $startAt->copy()->setTime(14, 0);
        $afternoonEnd = $startAt->copy()->setTime(17, 0);

        $inMorning = $startAt->gte($morningStart) && $endAt->lte($morningEnd);
        $inAfternoon = $startAt->gte($afternoonStart) && $endAt->lte($afternoonEnd);

        return ! ($inMorning || $inAfternoon);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function meetingWindow(Meeting $meeting): array
    {
        if (! $meeting->meeting_date) {
            return [null, null];
        }

        $date = Carbon::parse($meeting->meeting_date->toDateString());
        $startTime = $meeting->meeting_time ?: '09:00:00';
        $endTime = $meeting->end_time;

        $start = Carbon::parse($date->toDateString().' '.$startTime);
        if ($endTime) {
            $end = Carbon::parse($date->toDateString().' '.$endTime);
        } else {
            $end = $start->copy()->addHour();
        }

        if ($end->lte($start)) {
            $end = $start->copy()->addHour();
        }

        return [$start, $end];
    }
}
