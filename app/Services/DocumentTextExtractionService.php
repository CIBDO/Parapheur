<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentIndexContent;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Extraction de texte légère (sans dépendances externes lourdes).
 * Formats : TXT, DOCX (XML), XLSX (sharedStrings), PPTX (slides), PDF basique.
 */
class DocumentTextExtractionService
{
    public function extractFromVersion(DocumentVersion $version): ?string
    {
        try {
            if (! Storage::disk($version->disk)->exists($version->path)) {
                return null;
            }

            $binary = Storage::disk($version->disk)->get($version->path);
            $name = strtolower($version->original_name ?: '');
            $mime = strtolower((string) $version->mime_type);

            if (str_ends_with($name, '.txt') || str_contains($mime, 'text/plain')) {
                return $this->normalize($binary);
            }

            if (str_ends_with($name, '.docx') || str_contains($mime, 'wordprocessingml')) {
                return $this->normalize($this->extractFromDocx($binary));
            }

            if (str_ends_with($name, '.xlsx') || str_contains($mime, 'spreadsheetml')) {
                return $this->normalize($this->extractFromXlsx($binary));
            }

            if (str_ends_with($name, '.pptx') || str_contains($mime, 'presentationml')) {
                return $this->normalize($this->extractFromPptx($binary));
            }

            if (str_ends_with($name, '.pdf') || str_contains($mime, 'pdf')) {
                return $this->normalize($this->extractFromPdfRough($binary));
            }

            return null;
        } catch (Throwable $e) {
            Log::warning('GED extraction échouée', [
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function indexDocument(Document $document): DocumentIndexContent
    {
        $version = $document->latestVersion ?: $document->versions()->orderByDesc('version_number')->first();
        $meta = trim(implode("\n", array_filter([
            $document->reference,
            $document->dossier_number,
            $document->title,
            $document->object,
            $document->description,
            $document->summary,
            is_array($document->keywords) ? implode(' ', $document->keywords) : null,
        ])));

        $fileText = $version ? ($this->extractFromVersion($version) ?? '') : '';
        $content = trim($meta."\n".$fileText);
        $method = $fileText !== '' ? 'native' : 'metadata_only';

        $index = DocumentIndexContent::query()->updateOrCreate(
            [
                'document_id' => $document->id,
                'document_version_id' => $version?->id,
            ],
            [
                'content' => mb_substr($content, 0, 500000),
                'extraction_method' => $method,
                'extracted_at' => now(),
            ]
        );

        $document->forceFill([
            'text_extraction_status' => $fileText !== '' ? 'done' : 'metadata_only',
            'indexed_at' => now(),
            'antivirus_status' => $document->antivirus_status ?: 'pending',
        ])->saveQuietly();

        return $index;
    }

    private function extractFromDocx(string $binary): string
    {
        return $this->extractZipXmlText($binary, '#^word/document\\.xml$#');
    }

    private function extractFromXlsx(string $binary): string
    {
        return $this->extractZipXmlText($binary, '#^xl/(sharedStrings|worksheets/sheet\\d+)\\.xml$#');
    }

    private function extractFromPptx(string $binary): string
    {
        return $this->extractZipXmlText($binary, '#^ppt/slides/slide\\d+\\.xml$#');
    }

    private function extractZipXmlText(string $binary, string $pathPattern): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gedx');
        file_put_contents($tmp, $binary);
        $zip = new ZipArchive;
        $text = '';
        if ($zip->open($tmp) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! $name || ! preg_match($pathPattern, $name)) {
                    continue;
                }
                $xml = $zip->getFromIndex($i);
                if ($xml === false) {
                    continue;
                }
                $text .= ' '.strip_tags(str_replace(['</w:p>', '</a:t>', '</t>'], "\n", $xml));
            }
            $zip->close();
        }
        @unlink($tmp);

        return $text;
    }

    /**
     * Extraction rudimentaire des chaînes PDF (sans OCR) — utile pour PDF textuels simples.
     */
    private function extractFromPdfRough(string $binary): string
    {
        $out = '';
        if (preg_match_all('/\\((?:\\\\.|[^\\\\)]){1,200}\\)/', $binary, $m)) {
            foreach ($m[0] as $chunk) {
                $s = substr($chunk, 1, -1);
                $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", '', ' ', '(', ')'], $s);
                if (preg_match('/[\\x20-\\x7E\\xC0-\\xFF]{3,}/u', $s)) {
                    $out .= ' '.$s;
                }
            }
        }

        return $out;
    }

    private function normalize(?string $text): string
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\\s+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
