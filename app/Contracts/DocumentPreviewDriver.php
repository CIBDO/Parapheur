<?php

namespace App\Contracts;

use App\Models\DocumentVersion;

/**
 * Point d'extension Temps 1 → Temps 2 (ONLYOFFICE).
 * Temps 1 : prévisualisation PDF native / téléchargement Office.
 * Temps 2 : mode onlyoffice_editor pour DOCX/XLSX/PPTX.
 */
interface DocumentPreviewDriver
{
    public function supports(DocumentVersion $version): bool;

    /**
     * @return array{
     *     mode: string,
     *     url?: string|null,
     *     download_only?: bool,
     *     config_url?: string|null,
     *     document_server_url?: string|null
     * }
     */
    public function preview(DocumentVersion $version, string $streamUrl, string $downloadUrl): array;
}
