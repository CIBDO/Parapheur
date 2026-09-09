<?php

namespace App\Services\Preview;

use App\Contracts\DocumentPreviewDriver;
use App\Models\DocumentVersion;

class NativeDocumentPreviewDriver implements DocumentPreviewDriver
{
    public function supports(DocumentVersion $version): bool
    {
        return true;
    }

    public function preview(DocumentVersion $version, string $streamUrl, string $downloadUrl): array
    {
        $mime = strtolower((string) $version->mime_type);
        $name = strtolower((string) $version->original_name);

        $isPdf = str_contains($mime, 'pdf') || str_ends_with($name, '.pdf');
        $isOffice = str_contains($mime, 'word')
            || str_contains($mime, 'excel')
            || str_contains($mime, 'powerpoint')
            || str_contains($mime, 'officedocument')
            || (bool) preg_match('/\.(docx?|xlsx?|pptx?)$/', $name);

        if ($isPdf) {
            return [
                'mode' => 'pdf_iframe',
                'url' => $streamUrl,
                'download_only' => false,
            ];
        }

        if ($isOffice) {
            return [
                'mode' => 'office_download',
                'url' => $downloadUrl,
                'download_only' => true,
            ];
        }

        return [
            'mode' => 'download',
            'url' => $downloadUrl,
            'download_only' => true,
        ];
    }
}
