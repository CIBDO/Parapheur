<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\MeetingStatus;
use App\Models\Appointment;
use App\Models\CalendarUnavailability;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;

class CalendarAggregationService
{
    public function __construct(
        private AppointmentAccessService $appointmentAccess,
        private MeetingAccessService $meetingAccess,
        private CalendarConflictService $conflicts,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function events(User $user, Carbon $from, Carbon $to, array $filters = []): array
    {
        $events = [];
        $sources = $this->normalizeSources($filters);

        if (in_array('appointment', $sources, true)) {
            $appointmentQuery = Appointment::query()
                ->with(['type', 'director'])
                ->whereNotNull('start_at')
                ->where('start_at', '<=', $to->copy()->endOfDay())
                ->where('end_at', '>=', $from->copy()->startOfDay())
                ->whereNotIn('status', [
                    AppointmentStatus::Refuse->value,
                    AppointmentStatus::Annule->value,
                    AppointmentStatus::Brouillon->value,
                    AppointmentStatus::Archive->value,
                ]);

            if (! empty($filters['director_id'])) {
                $appointmentQuery->where('director_id', $filters['director_id']);
            }
            if (! empty($filters['status'])) {
                $appointmentQuery->where('status', $filters['status']);
            }
            if (! empty($filters['appointment_type_id'])) {
                $appointmentQuery->where('appointment_type_id', $filters['appointment_type_id']);
            }
            if (! empty($filters['meeting_mode'])) {
                $appointmentQuery->where('meeting_mode', $filters['meeting_mode']);
            }
            if (! empty($filters['confidentiality'])) {
                $appointmentQuery->where('confidentiality', $filters['confidentiality']);
            }
            if (! empty($filters['structure_id'])) {
                $appointmentQuery->where('structure_id', $filters['structure_id']);
            }
            if (! empty($filters['q'])) {
                $q = '%'.$filters['q'].'%';
                $appointmentQuery->where(function ($builder) use ($q) {
                    $builder->where('subject', 'like', $q)
                        ->orWhere('reference', 'like', $q)
                        ->orWhere('requester_name', 'like', $q)
                        ->orWhere('requester_organization', 'like', $q)
                        ->orWhere('location', 'like', $q);
                });
            }

            foreach ($appointmentQuery->get() as $appointment) {
                /** @var Appointment $appointment */
                $canView = $this->appointmentAccess->canView($user, $appointment);
                $canSeeDetails = $canView && $this->appointmentAccess->canSeeDetails($user, $appointment);

                if (! $canView && ! $appointment->blocks_calendar) {
                    continue;
                }
                if (! $canView && ! $user->can('appointments.view_calendar') && ! $user->can('appointments.view')) {
                    continue;
                }

                $masked = ! $canSeeDetails;
                $events[] = [
                    'id' => 'appointment-'.$appointment->id,
                    'source' => 'appointment',
                    'source_id' => $appointment->id,
                    'title' => $masked ? 'Indisponible' : $appointment->displaySubject(),
                    'start_at' => $appointment->start_at->toIso8601String(),
                    'end_at' => $appointment->end_at->toIso8601String(),
                    'date' => $appointment->start_at->toDateString(),
                    'time' => $appointment->start_at->format('H:i'),
                    'end_time' => $appointment->end_at->format('H:i'),
                    'status' => $appointment->status?->value,
                    'type' => $masked ? null : $appointment->type?->name,
                    'location' => $masked ? null : $appointment->location,
                    'confidentiality' => $appointment->confidentiality?->value,
                    'blocks_calendar' => $appointment->blocks_calendar,
                    'masked' => $masked,
                    'url' => $masked ? null : '/parapheur/agenda/'.$appointment->id,
                    'color_token' => 'appointment',
                ];
            }
        }

        if (in_array('meeting', $sources, true)) {
            $meetingQuery = $this->meetingAccess->visibleQuery($user)
                ->with(['type', 'chair'])
                ->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()])
                ->whereNotIn('status', [
                    MeetingStatus::Annulee->value,
                    MeetingStatus::Archivee->value,
                    MeetingStatus::Brouillon->value,
                ]);

            if (! empty($filters['director_id'])) {
                $meetingQuery->where('chair_id', $filters['director_id']);
            }
            if (! empty($filters['structure_id'])) {
                $meetingQuery->where('structure_id', $filters['structure_id']);
            }
            if (! empty($filters['confidentiality'])) {
                $meetingQuery->where('confidentiality', $filters['confidentiality']);
            }

            foreach ($meetingQuery->get() as $meeting) {
                /** @var Meeting $meeting */
                [$start, $end] = $this->conflicts->meetingWindow($meeting);
                if (! $start || ! $end) {
                    continue;
                }

                $events[] = [
                    'id' => 'meeting-'.$meeting->id,
                    'source' => 'meeting',
                    'source_id' => $meeting->id,
                    'title' => $meeting->displayTitle(),
                    'start_at' => $start->toIso8601String(),
                    'end_at' => $end->toIso8601String(),
                    'date' => $start->toDateString(),
                    'time' => $start->format('H:i'),
                    'end_time' => $end->format('H:i'),
                    'status' => $meeting->status?->value,
                    'type' => $meeting->type?->name,
                    'location' => $meeting->location,
                    'confidentiality' => $meeting->confidentiality?->value,
                    'blocks_calendar' => true,
                    'masked' => false,
                    'url' => '/parapheur/reunions/'.$meeting->id,
                    'color_token' => 'meeting',
                ];
            }
        }

        if (in_array('unavailability', $sources, true)) {
            $unavailQuery = CalendarUnavailability::query()
                ->where('is_active', true)
                ->where('start_at', '<=', $to->copy()->endOfDay())
                ->where('end_at', '>=', $from->copy()->startOfDay());

            if (! empty($filters['director_id'])) {
                $unavailQuery->where('user_id', $filters['director_id']);
            }
            if (! empty($filters['confidentiality'])) {
                $unavailQuery->where('confidentiality', $filters['confidentiality']);
            }

            $canSeeDetails = $user->can('appointments.manage_unavailability')
                || $user->can('appointments.manage_calendar')
                || $user->can('admin.access')
                || $user->can('dashboard.dg');

            foreach ($unavailQuery->get() as $slot) {
                $confidential = in_array(
                    $slot->confidentiality?->value ?? $slot->confidentiality,
                    [DocumentConfidentiality::Confidentiel->value, DocumentConfidentiality::TresConfidentiel->value],
                    true
                );
                $showDetails = $canSeeDetails || (int) $slot->user_id === (int) $user->id || ! $confidential;

                $events[] = [
                    'id' => 'unavailability-'.$slot->id,
                    'source' => 'unavailability',
                    'source_id' => $slot->id,
                    'title' => $showDetails ? $slot->title : 'INDISPONIBLE',
                    'start_at' => $slot->start_at->toIso8601String(),
                    'end_at' => $slot->end_at->toIso8601String(),
                    'date' => $slot->start_at->toDateString(),
                    'time' => $slot->start_at->format('H:i'),
                    'end_time' => $slot->end_at->format('H:i'),
                    'status' => $slot->kind?->value,
                    'type' => $showDetails ? ($slot->kind?->label()) : 'Indisponibilité',
                    'location' => $showDetails ? $slot->location : null,
                    'confidentiality' => $slot->confidentiality?->value,
                    'blocks_calendar' => $slot->blocks_calendar,
                    'masked' => ! $showDetails,
                    'url' => null,
                    'color_token' => 'unavailability',
                ];
            }
        }

        usort($events, fn ($a, $b) => strcmp($a['start_at'], $b['start_at']));

        return $events;
    }

    /**
     * @return list<string>
     */
    private function normalizeSources(array $filters): array
    {
        if (! empty($filters['sources'])) {
            $raw = is_array($filters['sources'])
                ? $filters['sources']
                : explode(',', (string) $filters['sources']);

            return array_values(array_intersect(
                ['appointment', 'meeting', 'unavailability'],
                array_map('trim', $raw)
            )) ?: ['appointment', 'meeting', 'unavailability'];
        }

        $sources = [];
        if ($this->flagEnabled($filters, 'include_appointments', true)) {
            $sources[] = 'appointment';
        }
        if ($this->flagEnabled($filters, 'include_meetings', true)) {
            $sources[] = 'meeting';
        }
        if ($this->flagEnabled($filters, 'include_unavailabilities', true)) {
            $sources[] = 'unavailability';
        }

        return $sources ?: ['appointment', 'meeting', 'unavailability'];
    }

    private function flagEnabled(array $filters, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $filters)) {
            return $default;
        }

        $value = $filters[$key];
        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(strtolower((string) $value), ['0', 'false', 'no', 'off'], true);
    }
}
