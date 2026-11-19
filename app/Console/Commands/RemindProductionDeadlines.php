<?php

namespace App\Console\Commands;

use App\Services\Productions\ProductionReminderService;
use Illuminate\Console\Command;

class RemindProductionDeadlines extends Command
{
    protected $signature = 'parapheur:remind-productions';

    protected $description = 'Rappel unique du matin (07:30–10:00 Bamako) — email aux producteurs des éléments non encore envoyés';

    public function handle(ProductionReminderService $reminders): int
    {
        $result = $reminders->processMorningDigest();
        $this->info(sprintf(
            'Digest productions — emails: %d, ignorés: %d, échecs: %d',
            $result['sent'],
            $result['skipped'],
            $result['failed'] ?? 0,
        ));

        return ($result['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
