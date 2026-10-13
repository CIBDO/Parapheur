<?php

namespace App\Console\Commands;

use App\Services\Announcements\AnnouncementService;
use Illuminate\Console\Command;

class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'parapheur:publish-announcements';

    protected $description = 'Publie les annonces programmées dont l\'échéance est atteinte';

    public function handle(AnnouncementService $announcements): int
    {
        $count = $announcements->publishDue();
        $this->info('Annonces publiées : '.$count);

        return self::SUCCESS;
    }
}
