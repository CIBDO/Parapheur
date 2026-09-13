<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Console\Command;

class MigrateGedDocumentMetadataCommand extends Command
{
    protected $signature = 'ged:migrate-metadata {--dry-run : Simuler sans écrire}';

    protected $description = 'Enrichit les documents existants pour la GED (origin, tags depuis keywords, titre)';

    public function handle(DocumentService $documents): int
    {
        $dry = (bool) $this->option('dry-run');
        $count = 0;
        $tagged = 0;

        Document::query()->withTrashed()->orderBy('id')->chunkById(100, function ($chunk) use ($dry, $documents, &$count, &$tagged) {
            foreach ($chunk as $document) {
                $dirty = false;

                if (! $document->origin) {
                    $document->origin = 'parapheur';
                    $dirty = true;
                }

                if (! $document->title && $document->object) {
                    $document->title = $document->object;
                    $dirty = true;
                }

                if (! $document->archive_status) {
                    $document->archive_status = $document->archived_at ? 'archive' : 'actif';
                    $dirty = true;
                }

                if (! $document->owner_structure_id && $document->structure_id) {
                    $document->owner_structure_id = $document->structure_id;
                    $dirty = true;
                }

                if ($dirty && ! $dry) {
                    $document->saveQuietly();
                }

                $keywords = $document->keywords ?? [];
                if (is_array($keywords) && $keywords !== [] && $document->tags()->count() === 0) {
                    if (! $dry) {
                        $documents->syncTags($document, $keywords);
                    }
                    $tagged++;
                }

                $count++;
            }
        });

        $this->info(sprintf(
            '%s %d document(s) traités, %d avec tags depuis keywords.',
            $dry ? '[dry-run]' : 'OK',
            $count,
            $tagged
        ));

        return self::SUCCESS;
    }
}
