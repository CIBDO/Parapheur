<?php

namespace App\Services\Preview;

use App\Contracts\DocumentPreviewDriver;
use App\Models\DocumentVersion;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Support\Facades\URL;

/**
 * Temps 2 : DOCX/XLSX/PPTX → éditeur ONLYOFFICE ; PDF et reste → driver natif.
 */
class OnlyOfficeDocumentPreviewDriver implements DocumentPreviewDriver
{
    public function __construct(
        private readonly NativeDocumentPreviewDriver $native,
        private readonly OnlyOfficeService $onlyOffice,
    ) {}

    public function supports(DocumentVersion $version): bool
    {
        return true;
    }

    public function preview(DocumentVersion $version, string $streamUrl, string $downloadUrl): array
    {
        if ($this->onlyOffice->isEnabled() && $this->onlyOffice->isOfficeEditable($version)) {
            $document = $version->relationLoaded('document')
                ? $version->document
                : $version->document()->first();

            $configUrl = null;
            if ($document) {
                $configUrl = URL::to('/api/parapheur/documents/'.$document->id.'/onlyoffice/config');
            }

            return [
                'mode' => 'onlyoffice_editor',
                'url' => $downloadUrl,
                'download_only' => false,
                'config_url' => $configUrl,
                'document_server_url' => config('onlyoffice.url'),
            ];
        }

        return $this->native->preview($version, $streamUrl, $downloadUrl);
    }
}
