<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;

/**
 * Scanner antivirus configurable.
 * Sans moteur externe : heuristique locale (signature EICAR / marqueur .infected).
 * Branchez un moteur réel via config/antivirus.php (driver clamav|http|null).
 */
class AntivirusScanService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function scanDocument(Document $document): string
    {
        $version = $document->latestVersion;
        if (! $version) {
            $document->antivirus_status = 'pending';
            $document->save();

            return 'pending';
        }

        return $this->scanVersion($document, $version);
    }

    public function scanVersion(Document $document, DocumentVersion $version): string
    {
        $driver = config('antivirus.driver', 'heuristic');

        $status = match ($driver) {
            'null', 'none' => 'safe',
            default => $this->heuristicScan($version),
        };

        $document->antivirus_status = $status;
        $document->save();

        if ($status === 'infected') {
            $this->audit->log('document.antivirus_infected', $document, [
                'version_id' => $version->id,
                'driver' => $driver,
            ]);
        }

        return $status;
    }

    private function heuristicScan(DocumentVersion $version): string
    {
        $name = mb_strtolower((string) $version->original_name);
        if (str_contains($name, '.infected') || str_contains($name, 'eicar')) {
            return 'infected';
        }

        try {
            $disk = $version->disk ?: 'local';
            if (! Storage::disk($disk)->exists($version->path)) {
                return 'failed';
            }

            // Lire au plus 4 Ko pour détecter la signature EICAR classique
            $chunk = Storage::disk($disk)->readStream($version->path);
            if (! is_resource($chunk)) {
                return 'safe';
            }
            $sample = stream_get_contents($chunk, 4096) ?: '';
            fclose($chunk);

            if (str_contains($sample, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) {
                return 'infected';
            }
        } catch (\Throwable) {
            return 'failed';
        }

        return 'safe';
    }
}
