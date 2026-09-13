<?php

namespace App\Services;

use ZipArchive;
use RuntimeException;

/**
 * Simple DOCX template renderer using ZipArchive
 * Supports {{variable}} replacements and {{#array}}...{{/array}} simple loops
 * Phase 1.5 will add phpoffice/phpword for advanced features
 */
class DocumentTemplateRenderer
{
    public function render(string $templatePath, array $variables): string
    {
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template file not found: {$templatePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($templatePath) !== true) {
            throw new RuntimeException("Cannot open template file: {$templatePath}");
        }

        // Read document.xml
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new RuntimeException("Invalid DOCX template: word/document.xml not found");
        }

        // Replace variables
        $processedXml = $this->replaceVariables($documentXml, $variables);

        // Create output file
        $outputPath = storage_path('app/temp/' . uniqid('rendered_', true) . '.docx');
        $outputDir = dirname($outputPath);
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        copy($templatePath, $outputPath);
        $zip->close();

        // Update output file with processed content
        $outputZip = new ZipArchive();
        if ($outputZip->open($outputPath) !== true) {
            throw new RuntimeException("Cannot open output file: {$outputPath}");
        }

        $outputZip->deleteName('word/document.xml');
        $outputZip->addFromString('word/document.xml', $processedXml);
        $outputZip->close();

        return $outputPath;
    }

    protected function replaceVariables(string $xml, array $variables): string
    {
        // First pass: simple {{variable}} replacements
        foreach ($variables as $key => $value) {
            if (!is_array($value)) {
                $xml = str_replace('{{' . $key . '}}', $this->escapeXml((string)$value), $xml);
            }
        }

        // Second pass: {{#array}}...{{/array}} loops
        $xml = preg_replace_callback(
            '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            function ($matches) use ($variables) {
                $arrayKey = $matches[1];
                $template = $matches[2];
                
                if (!isset($variables[$arrayKey]) || !is_array($variables[$arrayKey])) {
                    return '';
                }

                $result = '';
                foreach ($variables[$arrayKey] as $item) {
                    $itemResult = $template;
                    if (is_array($item)) {
                        foreach ($item as $itemKey => $itemValue) {
                            $itemResult = str_replace('{{' . $itemKey . '}}', $this->escapeXml((string)$itemValue), $itemResult);
                        }
                    }
                    $result .= $itemResult;
                }

                return $result;
            },
            $xml
        );

        return $xml;
    }

    protected function escapeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Crée un document DOCX minimal avec un titre et des paragraphes.
     * Utilisé comme fallback quand aucun template n'est disponible.
     *
     * @param  array<string>  $paragraphs
     */
    public function createBlankDocx(string $title, array $paragraphs): string
    {
        $outputPath = storage_path('app/temp/'.uniqid('blank_', true).'.docx');
        $outputDir = dirname($outputPath);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Cannot create blank DOCX: {$outputPath}");
        }

        // Content Types
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');

        // _rels/.rels
        $zip->addEmptyDir('_rels');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

        // word/_rels/document.xml.rels
        $zip->addEmptyDir('word');
        $zip->addEmptyDir('word/_rels');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>');

        // Build document.xml
        $paragraphsXml = '';
        foreach ($paragraphs as $para) {
            $escaped = $this->escapeXml($para);
            $paragraphsXml .= '<w:p><w:r><w:t>'.$escaped.'</w:t></w:r></w:p>';
        }

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t>'.$this->escapeXml($title).'</w:t></w:r></w:p>
        '.$paragraphsXml.'
    </w:body>
</w:document>');

        $zip->close();

        return $outputPath;
    }
}
