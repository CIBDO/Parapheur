<?php

namespace App\Console\Commands;

use App\Jobs\GenerateProductionOccurrencesJob;
use App\Models\User;
use App\Services\Productions\ProductionOccurrenceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateProductionOccurrences extends Command
{
    protected $signature = 'parapheur:generate-productions {--date= : Date cible Y-m-d} {--sync : Exécuter dans le processus courant}';

    protected $description = 'Génère les occurrences de production (job idempotent, repris en cas d’échec)';

    public function handle(ProductionOccurrenceService $occurrences): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();

        $actorId = User::query()->orderBy('id')->value('id');

        if ($this->option('sync')) {
            $result = $occurrences->generateForDate($date, $actorId ? User::query()->find($actorId) : null);
            $this->info(sprintf(
                'Génération %s — créées: %d, ignorées: %d (run #%d)',
                $date->toDateString(),
                $result['created'],
                $result['skipped'],
                $result['run_id']
            ));

            return self::SUCCESS;
        }

        GenerateProductionOccurrencesJob::dispatch($date->toDateString(), $actorId ? (int) $actorId : null);
        $this->info('Génération '.$date->toDateString().' envoyée dans la file.');

        return self::SUCCESS;
    }
}
