<?php

namespace App\Console\Commands;

use App\Jobs\CheckTicketSlaJob;
use Illuminate\Console\Command;

class CheckTicketSlaCommand extends Command
{
    protected $signature = 'parapheur:check-ticket-sla';

    protected $description = 'Vérifie les SLA tickets (warnings et breaches)';

    public function handle(): int
    {
        $this->info('Vérification des SLA tickets…');
        CheckTicketSlaJob::dispatchSync();
        $this->info('Terminé.');

        return self::SUCCESS;
    }
}
