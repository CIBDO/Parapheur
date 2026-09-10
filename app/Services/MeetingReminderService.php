<?php

namespace App\Services;

use App\Enums\MeetingDecisionStatus;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingDecision;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\MeetingNotification;
use Illuminate\Support\Facades\DB;

class MeetingReminderService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function dispatchDueReminders(): int
    {
        $count = 0;
        $count += $this->remindConfirmations();
        $count += $this->remindMeetings();
        $count += $this->remindDecisions();

        return $count;
    }

    private function remindConfirmations(): int
    {
        $days = config('meetings.reminders.confirmation_days_before', [3, 1]);
        $count = 0;

        $meetings = Meeting::query()
            ->with(['participants.user'])
            ->whereIn('status', [
                MeetingStatus::Convoquee->value,
                MeetingStatus::ConfirmationEnCours->value,
            ])
            ->whereDate('meeting_date', '>=', now()->toDateString())
            ->get();

        foreach ($meetings as $meeting) {
            $diff = now()->startOfDay()->diffInDays($meeting->meeting_date->copy()->startOfDay(), false);
            if (! in_array((int) $diff, array_map('intval', $days), true)) {
                continue;
            }
            $slot = 'J-'.$diff;
            foreach ($meeting->participants as $participant) {
                if (! $participant->user || $participant->confirmation_status) {
                    continue;
                }
                if ($this->alreadySent($meeting->id, null, $participant->user_id, 'confirmation', $slot)) {
                    continue;
                }
                $participant->user->notify(new MeetingNotification(
                    $meeting,
                    'confirmation_reminder',
                    'Merci de confirmer votre participation à la réunion « '.$meeting->displayTitle().' ».'
                ));
                $participant->last_reminded_at = now();
                $participant->save();
                $this->markSent($meeting->id, null, $participant->user_id, 'confirmation', $slot);
                $count++;
            }
        }

        return $count;
    }

    private function remindMeetings(): int
    {
        $days = config('meetings.reminders.meeting_days_before', [3, 1]);
        $hours = config('meetings.reminders.meeting_hours_before', [1]);
        $count = 0;

        $meetings = Meeting::query()
            ->with(['participants.user'])
            ->whereIn('status', [
                MeetingStatus::Convoquee->value,
                MeetingStatus::ConfirmationEnCours->value,
                MeetingStatus::Prete->value,
                MeetingStatus::Planifiee->value,
            ])
            ->whereDate('meeting_date', '>=', now()->subDay()->toDateString())
            ->get();

        foreach ($meetings as $meeting) {
            $diff = now()->startOfDay()->diffInDays($meeting->meeting_date->copy()->startOfDay(), false);
            $slot = null;
            if (in_array((int) $diff, array_map('intval', $days), true)) {
                $slot = 'J-'.$diff;
            } elseif ((int) $diff === 0 && $meeting->meeting_time) {
                $start = $meeting->meeting_date->copy()->setTimeFromTimeString((string) $meeting->meeting_time);
                foreach ($hours as $hour) {
                    $delta = now()->diffInHours($start, false);
                    if ($delta <= (int) $hour && $delta > 0) {
                        $slot = 'H-'.$hour;
                        break;
                    }
                }
            }
            if (! $slot) {
                continue;
            }

            foreach ($meeting->participants as $participant) {
                if (! $participant->user) {
                    continue;
                }
                if ($this->alreadySent($meeting->id, null, $participant->user_id, 'meeting', $slot)) {
                    continue;
                }
                $participant->user->notify(new MeetingNotification(
                    $meeting,
                    'meeting_reminder',
                    'Rappel : la réunion « '.$meeting->displayTitle().' » a lieu prochainement.'
                ));
                $this->markSent($meeting->id, null, $participant->user_id, 'meeting', $slot);
                $count++;
            }
        }

        return $count;
    }

    private function remindDecisions(): int
    {
        $days = config('meetings.reminders.decision_days_before', [7, 3, 1]);
        $count = 0;

        $decisions = MeetingDecision::query()
            ->with(['assignee', 'meeting'])
            ->whereIn('status', [
                MeetingDecisionStatus::AFaire->value,
                MeetingDecisionStatus::Planifiee->value,
                MeetingDecisionStatus::EnCours->value,
                MeetingDecisionStatus::EnAttente->value,
                MeetingDecisionStatus::PartiellementExecutee->value,
                MeetingDecisionStatus::EnRetard->value,
            ])
            ->whereNotNull('due_date')
            ->get();

        foreach ($decisions as $decision) {
            if (! $decision->assignee || ! $decision->meeting) {
                continue;
            }
            $diff = now()->startOfDay()->diffInDays($decision->due_date->copy()->startOfDay(), false);
            $slot = null;
            if ($diff < 0) {
                $slot = 'overdue';
                if ($decision->status !== MeetingDecisionStatus::EnRetard) {
                    $decision->status = MeetingDecisionStatus::EnRetard;
                    $decision->save();
                }
            } elseif (in_array((int) $diff, array_map('intval', $days), true)) {
                $slot = 'J-'.$diff;
            }
            if (! $slot) {
                continue;
            }
            $slot = $slot.'#'.$decision->id;
            if ($this->alreadySent($decision->meeting_id, $decision->id, $decision->assignee_id, 'decision', $slot)) {
                continue;
            }

            $decision->assignee->notify(new MeetingNotification(
                $decision->meeting,
                $slot === 'overdue' ? 'decision_late' : 'decision_assigned',
                $slot === 'overdue'
                    ? 'La décision « '.$decision->title.' » est en retard.'
                    : 'Échéance proche pour la décision « '.$decision->title.' ».'
            ));
            $decision->last_reminded_at = now();
            $decision->save();
            $this->markSent($decision->meeting_id, $decision->id, $decision->assignee_id, 'decision', $slot);
            $this->audit->log('meeting.decision_reminded', $decision, ['slot' => $slot]);
            $count++;
        }

        return $count;
    }

    private function alreadySent(?int $meetingId, ?int $decisionId, ?int $userId, string $kind, string $slot): bool
    {
        return DB::table('meeting_reminder_logs')
            ->where('meeting_id', $meetingId)
            ->where('meeting_decision_id', $decisionId)
            ->where('user_id', $userId)
            ->where('kind', $kind)
            ->where('slot', $slot)
            ->exists();
    }

    private function markSent(?int $meetingId, ?int $decisionId, ?int $userId, string $kind, string $slot): void
    {
        DB::table('meeting_reminder_logs')->insert([
            'meeting_id' => $meetingId,
            'meeting_decision_id' => $decisionId,
            'user_id' => $userId,
            'kind' => $kind,
            'slot' => $slot,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
