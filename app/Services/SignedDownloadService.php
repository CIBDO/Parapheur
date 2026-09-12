<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * URLs signées avec jti anti-rejeu (téléchargements / streaming).
 */
class SignedDownloadService
{
    public const PURPOSE_DOWNLOAD = 'download';

    public const PURPOSE_STREAM = 'stream';

    public const PURPOSE_ONLYOFFICE = 'onlyoffice';

    /**
     * @return array{document: int, version?: int, attachment?: int, user: int, jti: string}
     */
    public function paramsForVersion(
        Document $document,
        int $versionId,
        User $user,
        string $purpose,
    ): array {
        return $this->issue($purpose, [
            'document' => $document->id,
            'version' => $versionId,
            'user' => $user->id,
        ], $document->id, $user->id);
    }

    /**
     * @return array{document: int, attachment: int, user: int, jti: string}
     */
    public function paramsForAttachment(
        Document $document,
        int $attachmentId,
        User $user,
    ): array {
        return $this->issue(self::PURPOSE_DOWNLOAD, [
            'document' => $document->id,
            'attachment' => $attachmentId,
            'user' => $user->id,
        ], $document->id, $user->id);
    }

    public function ttlMinutes(string $purpose): int
    {
        return match ($purpose) {
            self::PURPOSE_STREAM => (int) config('parapheur.signed_urls.stream_ttl_minutes', 15),
            self::PURPOSE_ONLYOFFICE => (int) config('parapheur.signed_urls.onlyoffice_ttl_minutes', 30),
            default => (int) config('parapheur.signed_urls.download_ttl_minutes', 15),
        };
    }

    /**
     * Valide et consomme le jti. $allowedPurposes limite les usages acceptés pour l’endpoint.
     *
     * @param  list<string>  $allowedPurposes
     */
    public function consume(Request $request, Document $document, User $user, array $allowedPurposes): void
    {
        $jti = (string) $request->query('jti', '');
        abort_unless($jti !== '', 403, 'Lien de téléchargement invalide (jti manquant).');

        $key = $this->cacheKey($jti);
        $payload = Cache::get($key);
        abort_unless(is_array($payload), 403, 'Lien expiré ou déjà utilisé.');

        $purpose = (string) ($payload['purpose'] ?? '');
        abort_unless(
            in_array($purpose, $allowedPurposes, true)
            && (int) ($payload['document_id'] ?? 0) === (int) $document->id
            && (int) ($payload['user_id'] ?? 0) === (int) $user->id,
            403,
            'Lien de téléchargement invalide.'
        );

        $uses = (int) ($payload['uses'] ?? 1) - 1;
        if ($uses <= 0) {
            Cache::forget($key);

            return;
        }

        $ttl = max(1, (int) ($payload['ttl_minutes'] ?? $this->ttlMinutes($purpose)));
        Cache::put($key, array_merge($payload, ['uses' => $uses]), now()->addMinutes($ttl));
    }

    /**
     * @param  array<string, int>  $routeParams
     * @return array<string, int|string>
     */
    private function issue(string $purpose, array $routeParams, int $documentId, int $userId): array
    {
        $jti = Str::random(40);
        $ttl = $this->ttlMinutes($purpose);
        $uses = match ($purpose) {
            self::PURPOSE_STREAM => (int) config('parapheur.signed_urls.stream_max_uses', 12),
            self::PURPOSE_ONLYOFFICE => (int) config('parapheur.signed_urls.onlyoffice_max_uses', 8),
            default => 1,
        };

        Cache::put($this->cacheKey($jti), [
            'purpose' => $purpose,
            'document_id' => $documentId,
            'user_id' => $userId,
            'uses' => max(1, $uses),
            'ttl_minutes' => $ttl,
        ], now()->addMinutes($ttl));

        return array_merge($routeParams, ['jti' => $jti]);
    }

    private function cacheKey(string $jti): string
    {
        return 'signed_dl:'.$jti;
    }
}
