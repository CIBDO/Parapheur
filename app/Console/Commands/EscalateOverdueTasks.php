<?php

namespace App\Console\Commands;

use App\Services\Tasks\TaskEscalationService;
use Illuminate\Console\Command;

class EscalateOverdueTasks extends Command
{
    protected $signature = 'parapheur:escalate-tasks';

    protected $description = 'Escalade automatique des tâches en retard selon config/tasks.php';

    public function handle(TaskEscalationService $escalations): int
    {
        $count = $escalations->processDueEscalations();
        $this->info("Escalades effectuées : {$count}");

        return self::SUCCESS;
    }
}
