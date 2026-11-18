<?php

namespace App\Console\Commands;

use App\Jobs\VerifyArchiveIntegrityJob;
use App\Services\Archives\ArchiveIntegrityService;
use Illuminate\Console\Command;

class VerifyArchiveIntegrityCommand extends Command
{
    protected $signature = 'archives:verify-integrity {--limit=100 : Nombre max de records} {--queue : Dispatch en file}';

    protected $description = 'Contrôle périodique d’intégrité SHA-256 des archives conservées';

    public function handle(ArchiveIntegrityService $integrity): int
    {
        $limit = max(1, (int) $this->option('limit'));

        if ($this->option('queue')) {
            VerifyArchiveIntegrityJob::dispatch($limit);
            $this->info('Job VerifyArchiveIntegrityJob dispatché (limit='.$limit.').');

            return self::SUCCESS;
        }

        $result = $integrity->verifyBatch(null, $limit);
        $this->info(sprintf(
            'Contrôlés: %d | OK: %d | Échecs: %d',
            $result['checked'],
            $result['ok'],
            $result['failed']
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
