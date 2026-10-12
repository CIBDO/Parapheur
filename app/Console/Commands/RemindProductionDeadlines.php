<?php

namespace App\Console\Commands;

use App\Services\Productions\ProductionReminderService;
use Illuminate\Console\Command;

class RemindProductionDeadlines extends Command
{
    protected $signature = 'parapheur:remind-productions';

    protected $description = 'Envoie les rappels et escalades des productions (idempotent)';

    public function handle(ProductionReminderService $reminders): int
    {
        $result = $reminders->processDue();
        $this->info(sprintf('Rappels productions — envoyés: %d, ignorés: %d', $result['sent'], $result['skipped']));

        return self::SUCCESS;
    }
}
