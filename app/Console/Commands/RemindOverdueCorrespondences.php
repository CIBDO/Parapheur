<?php

namespace App\Console\Commands;

use App\Enums\CorrespondenceStatus;
use App\Models\Correspondence;
use App\Notifications\MailCorrespondenceNotification;
use App\Services\CorrespondenceReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemindOverdueCorrespondences extends Command
{
    protected $signature = 'parapheur:remind-mail';

    protected $description = 'Envoie les rappels de courrier en retard et traite les rappels dus';

    public function __construct(
        protected CorrespondenceReminderService $reminderService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔔 Traitement des rappels de courrier...');

        // 1. Traiter les rappels planifiés dus
        $remindersCount = $this->reminderService->processDueReminders();
        $this->info("✓ {$remindersCount} rappel(s) planifié(s) envoyé(s)");

        // 2. Notifier les assignés des correspondances en retard
        $overdueCount = $this->notifyOverdueAssignments();
        $this->info("✓ {$overdueCount} notification(s) de retard envoyée(s)");

        $this->info('✅ Traitement terminé');

        return self::SUCCESS;
    }

    /**
     * Notifie les utilisateurs des correspondances en retard.
     */
    protected function notifyOverdueAssignments(): int
    {
        $today = Carbon::today();
        $count = 0;

        $overdueCorrespondences = Correspondence::query()
            ->where('due_date', '<', $today)
            ->whereNotIn('status', [
                CorrespondenceStatus::Classe,
                CorrespondenceStatus::Archive,
                CorrespondenceStatus::Annule,
                CorrespondenceStatus::Expedie,
                CorrespondenceStatus::AccuseRecu,
            ])
            ->with(['assignments' => function ($query) {
                $query->whereIn('status', ['transmis', 'recu', 'consulte', 'pris_en_charge'])
                    ->with('toUser:id,name,email');
            }])
            ->get();

        foreach ($overdueCorrespondences as $correspondence) {
            $daysOverdue = $today->diffInDays($correspondence->due_date);

            foreach ($correspondence->assignments as $assignment) {
                if ($assignment->toUser) {
                    try {
                        $assignment->toUser->notify(new MailCorrespondenceNotification(
                            correspondence: $correspondence,
                            event: 'reminder',
                            message: sprintf(
                                'Ce courrier est en retard de %d jour(s). Échéance : %s',
                                $daysOverdue,
                                $correspondence->due_date->format('d/m/Y')
                            ),
                            actorName: 'Système de rappels'
                        ));

                        $count++;
                    } catch (\Exception $e) {
                        $this->error("Échec notification utilisateur {$assignment->toUser->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        return $count;
    }
}
