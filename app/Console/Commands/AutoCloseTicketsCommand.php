<?php

namespace App\Console\Commands;

use App\Jobs\AutoCloseResolvedTicketsJob;
use Illuminate\Console\Command;

class AutoCloseTicketsCommand extends Command
{
    protected $signature = 'parapheur:auto-close-tickets';

    protected $description = 'Clôture automatique des tickets résolus non validés';

    public function handle(): int
    {
        $this->info('Clôture automatique des tickets…');
        AutoCloseResolvedTicketsJob::dispatchSync();
        $this->info('Terminé.');

        return self::SUCCESS;
    }
}
