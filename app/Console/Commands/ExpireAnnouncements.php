<?php

namespace App\Console\Commands;

use App\Services\Announcements\AnnouncementService;
use Illuminate\Console\Command;

class ExpireAnnouncements extends Command
{
    protected $signature = 'parapheur:expire-announcements';

    protected $description = 'Fait expirer les annonces dont la date de fin est dépassée';

    public function handle(AnnouncementService $announcements): int
    {
        $count = $announcements->expireDue();
        $this->info('Annonces expirées : '.$count);

        return self::SUCCESS;
    }
}
