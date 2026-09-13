<?php

namespace App\Services;

use App\Models\Correspondence;
use App\Models\CorrespondenceReminder;
use App\Models\User;
use App\Notifications\MailCorrespondenceNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CorrespondenceReminderService
{
    /**
     * Crée un rappel manuel pour une correspondance.
     */
    public function createManual(Correspondence $correspondence, User $createdBy, array $data): CorrespondenceReminder
    {
        return DB::transaction(function () use ($correspondence, $createdBy, $data) {
            $reminder = $correspondence->reminders()->create([
                'user_id' => $data['user_id'] ?? $createdBy->id,
                'reminder_date' => $data['reminder_date'],
                'type' => $data['type'] ?? 'manual',
                'note' => $data['note'] ?? null,
                'is_sent' => false,
            ]);

            $correspondence->events()->create([
                'user_id' => $createdBy->id,
                'event_type' => 'reminder_created',
                'metadata' => [
                    'reminder_id' => $reminder->id,
                    'reminder_date' => $reminder->reminder_date->toDateString(),
                    'type' => $reminder->type,
                ],
                'comment' => $reminder->note,
                'created_at' => now(),
            ]);

            return $reminder;
        });
    }

    /**
     * Liste tous les rappels d'une correspondance.
     */
    public function list(Correspondence $correspondence): Collection
    {
        return $correspondence->reminders()
            ->with('user:id,name,email')
            ->orderBy('reminder_date')
            ->get();
    }

    /**
     * Traite tous les rappels dus (reminder_date <= aujourd'hui et pas encore envoyés).
     */
    public function processDueReminders(): int
    {
        $reminders = CorrespondenceReminder::query()
            ->where('reminder_date', '<=', Carbon::today())
            ->where('is_sent', false)
            ->with(['correspondence', 'user'])
            ->get();

        $count = 0;

        foreach ($reminders as $reminder) {
            try {
                // Notifier l'utilisateur
                $reminder->user->notify(new MailCorrespondenceNotification(
                    correspondence: $reminder->correspondence,
                    event: 'reminder',
                    message: $this->buildReminderMessage($reminder),
                    actorName: 'Système de rappels'
                ));

                // Marquer comme envoyé
                $reminder->update([
                    'is_sent' => true,
                    'sent_at' => now(),
                ]);

                $count++;
            } catch (\Exception $e) {
                logger()->error('Échec envoi rappel', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Planifie automatiquement des rappels pour une correspondance selon son échéance.
     */
    public function scheduleAutomatic(Correspondence $correspondence): int
    {
        if (! $correspondence->due_date) {
            return 0;
        }

        $dueDate = Carbon::parse($correspondence->due_date);
        $today = Carbon::today();

        // Ne créer que les rappels futurs
        $intervals = [
            ['days' => 3, 'label' => 'J-3'],
            ['days' => 1, 'label' => 'J-1'],
            ['days' => 0, 'label' => 'Jour J'],
        ];

        $count = 0;
        $assignedUsers = $this->getAssignedUsers($correspondence);

        foreach ($intervals as $interval) {
            $reminderDate = $dueDate->copy()->subDays($interval['days']);

            // Créer uniquement si date future et n'existe pas déjà
            if ($reminderDate->gte($today)) {
                foreach ($assignedUsers as $user) {
                    $exists = $correspondence->reminders()
                        ->where('user_id', $user->id)
                        ->where('reminder_date', $reminderDate)
                        ->where('type', 'deadline')
                        ->exists();

                    if (! $exists) {
                        $correspondence->reminders()->create([
                            'user_id' => $user->id,
                            'reminder_date' => $reminderDate,
                            'type' => 'deadline',
                            'note' => 'Rappel automatique échéance '.$interval['label'],
                            'is_sent' => false,
                        ]);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Obtient les utilisateurs assignés à une correspondance.
     */
    protected function getAssignedUsers(Correspondence $correspondence): Collection
    {
        $userIds = $correspondence->assignments()
            ->whereNotNull('to_user_id')
            ->whereIn('status', ['transmis', 'recu', 'consulte', 'pris_en_charge'])
            ->pluck('to_user_id')
            ->unique()
            ->filter();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()->whereIn('id', $userIds)->get();
    }

    /**
     * Construit le message du rappel.
     */
    protected function buildReminderMessage(CorrespondenceReminder $reminder): string
    {
        $corr = $reminder->correspondence;
        $daysUntilDue = $corr->due_date ? Carbon::today()->diffInDays($corr->due_date, false) : null;

        if ($reminder->type === 'deadline' && $daysUntilDue !== null) {
            if ($daysUntilDue < 0) {
                return sprintf(
                    'Rappel : ce courrier est en retard de %d jour(s). Échéance dépassée.',
                    abs($daysUntilDue)
                );
            } elseif ($daysUntilDue === 0) {
                return 'Rappel : ce courrier doit être traité aujourd\'hui.';
            } else {
                return sprintf(
                    'Rappel : ce courrier doit être traité dans %d jour(s).',
                    $daysUntilDue
                );
            }
        }

        return $reminder->note ?? 'Rappel concernant ce courrier.';
    }
}
