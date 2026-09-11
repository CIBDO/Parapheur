<?php

namespace App\Services\OnlyOffice;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\DocumentWorkflowService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use RuntimeException;

class OnlyOfficeService
{
    public function __construct(
        private readonly OnlyOfficeJwt $jwt,
        private readonly DocumentAccessService $access,
        private readonly DocumentWorkflowService $workflow,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('onlyoffice.enabled')
            && (string) config('onlyoffice.jwt_secret') !== '';
    }

    public function isOfficeEditable(DocumentVersion $version): bool
    {
        $name = strtolower((string) $version->original_name);
        $mime = strtolower((string) $version->mime_type);

        if (preg_match('/\.(docx|xlsx|pptx)$/', $name)) {
            return true;
        }

        return str_contains($mime, 'wordprocessingml')
            || str_contains($mime, 'spreadsheetml')
            || str_contains($mime, 'presentationml');
    }

    public function documentKey(Document $document, DocumentVersion $version): string
    {
        // Inclure un extrait du checksum pour invalider le cache DS après une ouverture en échec.
        $stamp = substr((string) $version->checksum, 0, 8) ?: 'nocheck';

        return $document->uuid.'-v'.$version->version_number.'-'.$stamp;
    }

    /**
     * Config DocsAPI pour le navigateur (avec JWT).
     *
     * @return array<string, mixed>
     */
    public function editorConfig(Document $document, User $user, ?DocumentVersion $version = null): array
    {
        if (! $this->isEnabled()) {
            throw new InvalidArgumentException('ONLYOFFICE désactivé.');
        }

        $version ??= $document->latestVersion;
        if (! $version) {
            throw new InvalidArgumentException('Aucune version principale.');
        }

        if (! $this->isOfficeEditable($version)) {
            throw new InvalidArgumentException('Format non éditable via ONLYOFFICE.');
        }

        $permissions = $this->permissionsFor($document, $user);
        $fileType = $this->fileType($version);
        $documentType = $this->documentType($fileType);
        $mode = ($permissions['edit'] || $permissions['review'] || $permissions['comment']) ? 'edit' : 'view';

        $fileUrl = $this->signedFileUrl($document, $version, $user);
        $callbackUrl = $this->appUrl('/api/onlyoffice/callback/'.$document->id);

        $config = [
            'documentType' => $documentType,
            'document' => [
                'title' => $this->safeTitle($version, $document, $fileType),
                'url' => $fileUrl,
                'fileType' => $fileType,
                'key' => $this->documentKey($document, $version),
                'permissions' => $permissions,
            ],
            'editorConfig' => [
                'mode' => $mode,
                'lang' => 'fr',
                'callbackUrl' => $callbackUrl,
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name ?: ('user-'.$user->id),
                ],
                'customization' => [
                    'forcesave' => true,
                    'autosave' => true,
                    'reviewDisplay' => 'markup',
                    'compactHeader' => false,
                ],
            ],
            'type' => 'desktop',
        ];

        $token = $this->jwt->encode($config);
        $config['token'] = $token;

        return [
            'enabled' => true,
            'document_server_url' => config('onlyoffice.url'),
            'api_script' => rtrim((string) config('onlyoffice.url'), '/').'/web-apps/apps/api/documents/api.js',
            'config' => $config,
            'version_id' => $version->id,
            'version_number' => $version->version_number,
            'can_edit' => (bool) $permissions['edit'],
            'can_review' => (bool) $permissions['review'],
            'can_comment' => (bool) $permissions['comment'],
        ];
    }

    /**
     * @return array{edit: bool, review: bool, comment: bool, download: bool, print: bool, fillForms: bool, modifyFilter: bool, modifyContentControl: bool, chat: bool}
     */
    public function permissionsFor(Document $document, User $user): array
    {
        $status = $document->status;
        $frozen = in_array($status, [DocumentStatus::Archive, DocumentStatus::Annule], true);

        $base = [
            'edit' => false,
            'review' => false,
            'comment' => false,
            'download' => true,
            'print' => true,
            'fillForms' => false,
            'modifyFilter' => true,
            'modifyContentControl' => true,
            'chat' => false,
            'copy' => true,
        ];

        if ($frozen) {
            return $base;
        }

        $canEdit = $this->access->canProcess($user, $document, 'act')
            || $this->access->canTransmit($user, $document);

        if ($canEdit) {
            return array_merge($base, [
                'edit' => true,
                'review' => true,
                'comment' => true,
            ]);
        }

        // Lecture seule : commenter / suggérer sans réécriture libre
        return array_merge($base, [
            'edit' => false,
            'review' => true,
            'comment' => true,
        ]);
    }

    /**
     * Historique pour refreshHistory (DocsAPI).
     *
     * @return array<string, mixed>
     */
    public function historyPayload(Document $document, User $user): array
    {
        $document->loadMissing('versions.uploader', 'latestVersion');
        $current = $document->latestVersion;

        $history = $document->versions->sortBy('version_number')->values()->map(function (DocumentVersion $version) use ($document) {
            return [
                'created' => optional($version->created_at)?->toIso8601String(),
                'key' => $this->documentKey($document, $version),
                'user' => [
                    'id' => (string) $version->uploaded_by,
                    'name' => $version->uploader?->name ?? 'Utilisateur',
                ],
                'version' => $version->version_number,
            ];
        })->all();

        return [
            'currentVersion' => $current?->version_number ?? 1,
            'history' => $history,
        ];
    }

    /**
     * Données d’une version pour setHistoryData.
     *
     * @return array<string, mixed>
     */
    public function historyData(Document $document, DocumentVersion $version, User $user): array
    {
        abort_unless($version->document_id === $document->id, 404);

        $payload = [
            'key' => $this->documentKey($document, $version),
            'url' => $this->signedFileUrl($document, $version, $user),
            'version' => $version->version_number,
            'fileType' => $this->fileType($version),
        ];

        $previous = $document->versions()
            ->where('version_number', '<', $version->version_number)
            ->orderByDesc('version_number')
            ->first();

        if ($previous) {
            $payload['previous'] = [
                'key' => $this->documentKey($document, $previous),
                'url' => $this->signedFileUrl($document, $previous, $user),
                'fileType' => $this->fileType($previous),
            ];
        }

        $token = $this->jwt->encode($payload);
        $payload['token'] = $token;

        return $payload;
    }

    /**
     * Fichier à comparer (onRequestCompareFile).
     *
     * @return array<string, mixed>
     */
    public function compareFile(Document $document, DocumentVersion $version, User $user): array
    {
        abort_unless($version->document_id === $document->id, 404);

        $payload = [
            'fileType' => $this->fileType($version),
            'url' => $this->signedFileUrl($document, $version, $user),
        ];
        $payload['token'] = $this->jwt->encode($payload);

        return $payload;
    }

    /**
     * Restaure une version antérieure en créant une nouvelle version (CDC §16).
     */
    public function restoreAsNewVersion(Document $document, DocumentVersion $version, User $user): DocumentVersion
    {
        abort_unless($version->document_id === $document->id, 404);

        if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
            throw new InvalidArgumentException('Document figé : restauration impossible.');
        }

        $absolute = Storage::disk($version->disk)->path($version->path);
        if (! is_file($absolute)) {
            throw new RuntimeException('Fichier de version introuvable.');
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            throw new RuntimeException('Lecture de version impossible.');
        }

        return $this->workflow->addVersionFromContents(
            $document,
            $user,
            $contents,
            $version->original_name,
            $version->mime_type ?: 'application/octet-stream',
            'Restauration de la version V'.$version->version_number.' (ONLYOFFICE)',
        );
    }

    /**
     * Traite le callback Document Server.
     *
     * @param  array<string, mixed>  $body
     * @return array{error: int}
     */
    public function handleCallback(Document $document, array $body, ?string $jwtToken): array
    {
        if (! $this->isEnabled()) {
            return ['error' => 1];
        }

        if ($jwtToken) {
            $claims = $this->jwt->decode($jwtToken);
            // Payload peut encapsuler le body dans "payload" selon versions DS
            if (isset($claims['payload']) && is_array($claims['payload'])) {
                $body = array_merge($body, $claims['payload']);
            } else {
                foreach (['status', 'url', 'key', 'users', 'actions'] as $field) {
                    if (array_key_exists($field, $claims) && ! array_key_exists($field, $body)) {
                        $body[$field] = $claims[$field];
                    }
                }
            }
        } elseif ((string) config('onlyoffice.jwt_secret') !== '') {
            throw new InvalidArgumentException('JWT ONLYOFFICE requis.');
        }

        $status = (int) ($body['status'] ?? 0);

        // 1 = édition en cours, 4 = fermé sans changement — rien à faire
        if (in_array($status, [1, 4], true)) {
            return ['error' => 0];
        }

        // 2 = prêt à sauver, 6 = forcesave
        if (! in_array($status, [2, 6], true)) {
            return ['error' => 0];
        }

        $url = (string) ($body['url'] ?? '');
        if ($url === '') {
            return ['error' => 0];
        }

        $url = $this->rewriteDocumentServerUrl($url);
        $response = Http::timeout(120)->withOptions(['allow_redirects' => true])->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Téléchargement fichier ONLYOFFICE impossible (HTTP '.$response->status().').');
        }

        $contents = $response->body();
        $current = $document->latestVersion;
        if ($current && hash('sha256', $contents) === $current->checksum) {
            return ['error' => 0];
        }

        if (in_array($document->status, [DocumentStatus::Archive, DocumentStatus::Annule], true)) {
            return ['error' => 0];
        }

        $actor = $this->resolveCallbackUser($body) ?? $current?->uploader;
        if (! $actor) {
            $actor = User::query()->find($document->author_id);
        }
        if (! $actor) {
            throw new RuntimeException('Impossible de déterminer l’auteur de la sauvegarde ONLYOFFICE.');
        }

        $originalName = $current?->original_name ?? ($document->object.'.docx');
        $mime = $current?->mime_type ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $this->workflow->addVersionFromContents(
            $document,
            $actor,
            $contents,
            $originalName,
            $mime,
            'Édition collaborative ONLYOFFICE',
        );

        return ['error' => 0];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function resolveCallbackUser(array $body): ?User
    {
        $ids = [];
        if (! empty($body['actions']) && is_array($body['actions'])) {
            foreach ($body['actions'] as $action) {
                if (is_array($action) && isset($action['userid'])) {
                    $ids[] = (string) $action['userid'];
                }
            }
        }
        if (! empty($body['users']) && is_array($body['users'])) {
            foreach ($body['users'] as $uid) {
                $ids[] = (string) $uid;
            }
        }

        $ids = array_values(array_filter(array_unique($ids)));
        if ($ids === []) {
            return null;
        }

        $last = end($ids);

        return User::query()->find((int) $last);
    }

    public function signedFileUrl(Document $document, DocumentVersion $version, User $user): string
    {
        $ttl = now()->addMinutes((int) config('onlyoffice.file_url_ttl_minutes', 120));
        $appUrl = rtrim((string) config('onlyoffice.app_url'), '/');
        $previousRoot = rtrim((string) config('app.url'), '/');

        // Signer avec l’URL vue par Document Server (sinon rewrite d’hôte invalide la signature).
        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }

        try {
            return URL::temporarySignedRoute(
                'documents.version.download',
                $ttl,
                [
                    'document' => $document->id,
                    'version' => $version->id,
                    'user' => $user->id,
                ]
            );
        } finally {
            URL::forceRootUrl($previousRoot !== '' ? $previousRoot : null);
        }
    }

    public function rewriteToAppUrl(string $url): string
    {
        $appUrl = rtrim((string) config('onlyoffice.app_url'), '/');
        if ($appUrl === '') {
            return $url;
        }

        $parts = parse_url($url);
        $appParts = parse_url($appUrl);
        if (! is_array($parts) || ! is_array($appParts)) {
            return $url;
        }

        $scheme = $appParts['scheme'] ?? 'http';
        $host = $appParts['host'] ?? 'localhost';
        $port = isset($appParts['port']) ? ':'.$appParts['port'] : '';
        $path = ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    /**
     * Si le DS renvoie une URL avec un hôte injoignable depuis Laravel, on normalise.
     */
    public function rewriteDocumentServerUrl(string $url): string
    {
        $ds = rtrim((string) config('onlyoffice.url'), '/');
        $parts = parse_url($url);
        $dsParts = parse_url($ds);
        if (! is_array($parts) || ! is_array($dsParts) || empty($parts['path'])) {
            return $url;
        }

        // Remplacer host/port par ceux de ONLYOFFICE_URL si le path Docs est reconnu
        if (str_contains($parts['path'], '/cache/') || str_contains($parts['path'], '/downloadfile/') || str_contains($url, 'onlyoffice')) {
            $scheme = $dsParts['scheme'] ?? ($parts['scheme'] ?? 'http');
            $host = $dsParts['host'] ?? ($parts['host'] ?? 'localhost');
            $port = isset($dsParts['port']) ? ':'.$dsParts['port'] : (isset($parts['port']) ? ':'.$parts['port'] : '');
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return $scheme.'://'.$host.$port.$parts['path'].$query;
        }

        return $url;
    }

    private function appUrl(string $path): string
    {
        return rtrim((string) config('onlyoffice.app_url'), '/').'/'.ltrim($path, '/');
    }

    public function fileType(DocumentVersion $version): string
    {
        $ext = strtolower(pathinfo((string) $version->original_name, PATHINFO_EXTENSION));
        if (in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
            return $ext;
        }

        $mime = strtolower((string) $version->mime_type);
        if (str_contains($mime, 'spreadsheetml') || str_contains($mime, 'excel')) {
            return 'xlsx';
        }
        if (str_contains($mime, 'presentationml') || str_contains($mime, 'powerpoint')) {
            return 'pptx';
        }

        return 'docx';
    }

    public function documentType(string $fileType): string
    {
        return match ($fileType) {
            'xlsx', 'xls', 'csv' => 'cell',
            'pptx', 'ppt' => 'slide',
            default => 'word',
        };
    }

    private function safeTitle(DocumentVersion $version, Document $document, string $fileType): string
    {
        $title = (string) ($version->original_name ?: ($document->object.'.'.$fileType));
        // Évite les titres trop exotiques qui perturbent certains builds Docs.
        $title = preg_replace('/[\r\n\t]+/', ' ', $title) ?? $title;
        $title = trim($title);

        return $title !== '' ? $title : ('document.'.$fileType);
    }
}
