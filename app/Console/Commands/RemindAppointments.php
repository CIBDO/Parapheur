<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;

class RemindAppointments extends Command
{
    protected $signature = 'parapheur:remind-appointments';

    protected $description = 'Relances rendez-vous DG (J-1 / H-2 / H-30)';

    public function handle(AppointmentReminderService $reminders): int
    {
        $count = $reminders->dispatchDueReminders();
        $this->info("Relances rendez-vous envoyées : {$count}");

        return self::SUCCESS;
    }
}
