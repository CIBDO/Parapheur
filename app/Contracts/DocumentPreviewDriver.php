<?php

namespace App\Contracts;

use App\Models\DocumentVersion;

/**
 * Point d'extension Temps 1 → Temps 2 (ONLYOFFICE).
 * Temps 1 : prévisualisation PDF native / téléchargement Office.
 */
interface DocumentPreviewDriver
{
    public function supports(DocumentVersion $version): bool;

    /**
     * @return array{mode: string, url?: string|null, download_only?: bool}
     */
    public function preview(DocumentVersion $version, string $streamUrl, string $downloadUrl): array;
}
