<?php

namespace App\Console\Commands;

use App\Services\Tasks\TaskReminderService;
use Illuminate\Console\Command;

class RemindTaskDeadlines extends Command
{
    protected $signature = 'parapheur:remind-task-deadlines';

    protected $description = 'Envoie les rappels et alertes de retard des tâches';

    public function handle(TaskReminderService $reminders): int
    {
        $sent = $reminders->processDueReminders();
        $this->info("Rappels tâches envoyés : {$sent}");

        return self::SUCCESS;
    }
}
