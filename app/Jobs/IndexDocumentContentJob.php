<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentTextExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class IndexDocumentContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $documentId,
    ) {}

    public function handle(DocumentTextExtractionService $extraction): void
    {
        $document = Document::query()->find($this->documentId);
        if (! $document) {
            return;
        }

        $extraction->indexDocument($document);
    }

    public function failed(?Throwable $e): void
    {
        Document::query()->whereKey($this->documentId)->update([
            'text_extraction_status' => 'failed',
        ]);
        report($e);
    }
}
