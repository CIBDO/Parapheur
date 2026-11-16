<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Scanner antivirus configurable.
 * Drivers : heuristic | null/none | clamav | http
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
        $driver = (string) config('antivirus.driver', 'heuristic');

        $status = match ($driver) {
            'null', 'none' => 'safe',
            'clamav' => $this->clamavScan($version),
            'http' => $this->httpScan($version),
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

            $chunk = Storage::disk($disk)->readStream($version->path);
            if (! is_resource($chunk)) {
                return 'safe';
            }
            $sample = stream_get_contents($chunk, 4096) ?: '';
            fclose($chunk);

            if (str_contains($sample, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) {
                return 'infected';
            }
        } catch (Throwable) {
            return 'failed';
        }

        return 'safe';
    }

    private function clamavScan(DocumentVersion $version): string
    {
        $disk = $version->disk ?: 'local';
        if (! Storage::disk($disk)->exists($version->path)) {
            return 'failed';
        }

        $absolute = Storage::disk($disk)->path($version->path);
        if (! is_file($absolute)) {
            // Disque distant : retomber sur scan HTTP stream via fichier temporaire
            return $this->clamavScanContents((string) Storage::disk($disk)->get($version->path));
        }

        return $this->clamavScanFile($absolute);
    }

    private function clamavScanFile(string $absolutePath): string
    {
        $socket = (string) config('antivirus.clamav.socket');
        $host = (string) config('antivirus.clamav.host', '127.0.0.1');
        $port = (int) config('antivirus.clamav.port', 3310);
        $timeout = (int) config('antivirus.clamav.timeout', 30);

        try {
            $fp = is_file($socket)
                ? @stream_socket_client('unix://'.$socket, $errno, $errstr, $timeout)
                : @stream_socket_client('tcp://'.$host.':'.$port, $errno, $errstr, $timeout);

            if (! is_resource($fp)) {
                Log::warning('ClamAV injoignable', ['error' => $errstr ?? 'n/a', 'errno' => $errno ?? 0]);

                return 'failed';
            }

            stream_set_timeout($fp, $timeout);
            fwrite($fp, "zINSTREAM\0");

            $handle = fopen($absolutePath, 'rb');
            if (! is_resource($handle)) {
                fclose($fp);

                return 'failed';
            }

            while (! feof($handle)) {
                $chunk = fread($handle, 2048);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($fp, pack('N', strlen($chunk)).$chunk);
            }
            fclose($handle);
            fwrite($fp, pack('N', 0));

            $response = stream_get_contents($fp) ?: '';
            fclose($fp);

            return $this->interpretClamResponse($response);
        } catch (Throwable $e) {
            Log::warning('ClamAV scan error', ['message' => $e->getMessage()]);

            return 'failed';
        }
    }

    private function clamavScanContents(string $contents): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'clam');
        if ($tmp === false) {
            return 'failed';
        }

        try {
            file_put_contents($tmp, $contents);

            return $this->clamavScanFile($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function interpretClamResponse(string $response): string
    {
        $normalized = strtoupper(trim($response));
        if (str_contains($normalized, 'FOUND')) {
            return 'infected';
        }
        if (str_contains($normalized, 'OK')) {
            return 'safe';
        }

        return 'failed';
    }

    private function httpScan(DocumentVersion $version): string
    {
        $url = rtrim((string) config('antivirus.http.url'), '/');
        if ($url === '') {
            Log::warning('ANTIVIRUS_HTTP_URL non configuré');

            return 'failed';
        }

        $disk = $version->disk ?: 'local';
        if (! Storage::disk($disk)->exists($version->path)) {
            return 'failed';
        }

        try {
            $stream = Storage::disk($disk)->readStream($version->path);
            if (! is_resource($stream)) {
                return 'failed';
            }

            $request = Http::timeout((int) config('antivirus.http.timeout', 60))
                ->attach('file', $stream, (string) $version->original_name);

            $token = (string) config('antivirus.http.token', '');
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($url);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $response->successful()) {
                return 'failed';
            }

            $json = $response->json();
            if (is_array($json) && isset($json['status'])) {
                $status = strtolower((string) $json['status']);

                return in_array($status, ['safe', 'infected', 'failed', 'pending'], true)
                    ? $status
                    : 'failed';
            }

            return $this->interpretClamResponse($response->body());
        } catch (Throwable $e) {
            Log::warning('Antivirus HTTP error', ['message' => $e->getMessage()]);

            return 'failed';
        }
    }
}
