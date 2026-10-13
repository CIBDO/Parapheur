<?php

namespace App\Console\Commands;

use App\Services\Announcements\AnnouncementNotificationService;
use Illuminate\Console\Command;

class RemindAnnouncementAcknowledgements extends Command
{
    protected $signature = 'parapheur:remind-announcements';

    protected $description = 'Relance les accusés de lecture d\'annonces encore ouverts';

    public function handle(AnnouncementNotificationService $notifications): int
    {
        $count = $notifications->remindPending();
        $this->info('Relances envoyées : '.$count);

        return self::SUCCESS;
    }
}
