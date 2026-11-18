<?php

namespace App\Jobs;

use App\Services\Archives\ArchiveIntegrityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyArchiveIntegrityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $limit = 100,
    ) {}

    public function handle(ArchiveIntegrityService $integrity): void
    {
        $result = $integrity->verifyBatch(null, $this->limit);

        Log::info('archives.integrity.batch', [
            'checked' => $result['checked'],
            'ok' => $result['ok'],
            'failed' => $result['failed'],
        ]);
    }

    public function failed(?Throwable $e): void
    {
        report($e);
    }
}
