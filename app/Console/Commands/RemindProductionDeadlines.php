<?php

namespace App\Console\Commands;

use App\Services\Productions\ProductionReminderService;
use Illuminate\Console\Command;

class RemindProductionDeadlines extends Command
{
    protected $signature = 'parapheur:remind-productions';

    protected $description = 'Rappel unique du matin (07:30) — email aux producteurs des éléments non encore envoyés';

    public function handle(ProductionReminderService $reminders): int
    {
        $result = $reminders->processMorningDigest();
        $this->info(sprintf('Digest productions 07:30 — emails: %d, ignorés: %d', $result['sent'], $result['skipped']));

        return self::SUCCESS;
    }
}
