<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Productions\ProductionOccurrenceService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProductionOccurrencesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $date,
        public ?int $actorId = null,
    ) {}

    public function handle(ProductionOccurrenceService $occurrences): void
    {
        $actor = $this->actorId
            ? User::query()->find($this->actorId)
            : User::query()->orderBy('id')->first();

        $occurrences->generateForDate(Carbon::parse($this->date)->startOfDay(), $actor);
    }
}
