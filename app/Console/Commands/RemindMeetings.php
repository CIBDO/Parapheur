<?php

namespace App\Console\Commands;

use App\Services\MeetingReminderService;
use Illuminate\Console\Command;

class RemindMeetings extends Command
{
    protected $signature = 'parapheur:remind-meetings';

    protected $description = 'Relances convocations, réunions et décisions (J-7 / J-3 / J-1 / H-1)';

    public function handle(MeetingReminderService $reminders): int
    {
        $count = $reminders->dispatchDueReminders();
        $this->info("Relances réunions envoyées : {$count}");

        return self::SUCCESS;
    }
}
