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

    /**
     * Fiche de circulation DGTCP — mise en page administrative soignée.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCirculationSheetDocx(array $data): string
    {
        return $this->writeOfficialDocx('fiche_circ_', $this->buildCirculationSheetDocumentXml($data));
    }

    /**
     * Bordereau de transmission interne (BT) — même charte que la fiche.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTransmissionSlipDocx(array $data): string
    {
        return $this->writeOfficialDocx('bordereau_bt_', $this->buildTransmissionSlipDocumentXml($data));
    }

    /**
     * Bordereau d'envoi / d'expédition (BE).
     *
     * @param  array<string, mixed>  $data
     */
    public function createDispatchSlipDocx(array $data): string
    {
        return $this->writeOfficialDocx('bordereau_be_', $this->buildDispatchSlipDocumentXml($data));
    }

    /**
     * Accusé de réception (AR).
     *
     * @param  array<string, mixed>  $data
     */
    public function createAcknowledgementDocx(array $data): string
    {
        return $this->writeOfficialDocx('accuse_ar_', $this->buildAcknowledgementDocumentXml($data));
    }

    private function writeOfficialDocx(string $prefix, string $documentXml): string
    {
        $outputPath = storage_path('app/temp/'.uniqid($prefix, true).'.docx');
        $outputDir = dirname($outputPath);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Cannot create official DOCX: {$outputPath}");
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>');

        $zip->addEmptyDir('_rels');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

        $zip->addEmptyDir('word');
        $zip->addEmptyDir('word/_rels');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/><w:sz w:val="20"/></w:rPr>
  </w:style>
</w:styles>');

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $outputPath;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildCirculationSheetDocumentXml(array $data): string
    {
        $conf = strtoupper((string) ($data['confidentialite_label'] ?? 'ORDINAIRE'));
        $confColor = match ($conf) {
            'CONFIDENTIEL' => '8B1A1A',
            'RESTREINT' => '9A5B00',
            default => '0B6B3A',
        };

        $metaPairs = [
            ['N° Enregistrement', $data['numero_enregistrement'] ?? '', 'Date d\'arrivée', $data['date_reception'] ?? ''],
            ['Origine', $data['origine'] ?? '', 'Référence', $data['reference_externe'] ?? ''],
            ['N° Fiche', $data['numero_fiche'] ?? '', 'Date du courrier', $data['date_correspondance'] ?? ''],
            ['Type', $data['type_courrier'] ?? '', 'Priorité', $data['priorite'] ?? ''],
            ['Destinataire(s)', $data['destinataires'] ?? '', 'Pièces', (string) ($data['nombre_pieces'] ?? '0')],
        ];

        $metaXml = '';
        foreach ($metaPairs as [$l1, $v1, $l2, $v2]) {
            $metaXml .= '<w:tr>'
                .$this->fcLabelCell($l1, 2100)
                .$this->fcValueCell($v1, 2900)
                .$this->fcLabelCell($l2, 2100)
                .$this->fcValueCell($v2, 2900)
                .'</w:tr>';
        }

        $imputations = is_array($data['imputations'] ?? null) ? $data['imputations'] : [];
        $annotations = is_array($data['annotations'] ?? null) ? $data['annotations'] : [];
        $affectations = is_array($data['affectations'] ?? null) ? $data['affectations'] : [];

        $affRows = '';
        if ($affectations === []) {
            $affRows = '<w:tr>'
                .$this->fcValueCell('Aucune affectation enregistrée à ce stade.', 10000, false)
                .'</w:tr>';
        } else {
            $affRows = '<w:tr>'
                .$this->fcLabelCell('Destinataire', 3500)
                .$this->fcLabelCell('Action', 2500)
                .$this->fcLabelCell('Instruction', 4000)
                .'</w:tr>';
            foreach ($affectations as $aff) {
                $affRows .= '<w:tr>'
                    .$this->fcValueCell($aff['destinataire'] ?? '', 3500)
                    .$this->fcValueCell($aff['action'] ?? '', 2500)
                    .$this->fcValueCell($aff['instruction'] ?? '', 4000)
                    .'</w:tr>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    '.$this->fcHeaderTable($data).'
    '.$this->fcSpacer(80).'
    '.$this->fcTitleBand('FICHE DE CIRCULATION COURRIER', $conf, $confColor).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('IDENTIFICATION DU COURRIER').'
    '.$this->fcBorderedTable($metaXml).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('OBJET').'
    '.$this->fcObjetBox((string) ($data['objet'] ?? '')).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('IMPUTATION').'
    '.$this->buildCheckboxGridXml($imputations, 2, true).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('ANNOTATION').'
    '.$this->buildCheckboxGridXml($annotations, 3, false).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('AFFECTATIONS / INSTRUCTIONS').'
    '.$this->fcBorderedTable($affRows).'
    '.$this->fcSpacer(160).'
    '.$this->fcSignaturesTable().'
    '.$this->fcSectPr().'
  </w:body>
</w:document>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildTransmissionSlipDocumentXml(array $data): string
    {
        $metaPairs = [
            ['N° Bordereau', $data['numero'] ?? '', 'Date', $data['date'] ?? ''],
            ['De (structure)', $data['expediteur'] ?? '', 'À (structure)', $data['destinataire'] ?? ''],
            ['Nature', $data['nature'] ?? '', 'Pièces totales', (string) ($data['total_pieces'] ?? '0')],
        ];

        $metaXml = '';
        foreach ($metaPairs as [$l1, $v1, $l2, $v2]) {
            $metaXml .= '<w:tr>'
                .$this->fcLabelCell($l1, 2100)
                .$this->fcValueCell((string) $v1, 2900)
                .$this->fcLabelCell($l2, 2100)
                .$this->fcValueCell((string) $v2, 2900)
                .'</w:tr>';
        }

        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $itemRows = '<w:tr>'
            .$this->fcLabelCell('N°', 800)
            .$this->fcLabelCell('Référence', 2400)
            .$this->fcLabelCell('Objet', 4800)
            .$this->fcLabelCell('Pièces', 1000)
            .$this->fcLabelCell('Obs.', 1466)
            .'</w:tr>';

        if ($items === []) {
            $itemRows .= '<w:tr>'.$this->fcValueCell('Aucun document listé.', 10466, false).'</w:tr>';
        } else {
            foreach ($items as $index => $item) {
                $itemRows .= '<w:tr>'
                    .$this->fcValueCell((string) ($index + 1), 800)
                    .$this->fcValueCell((string) ($item['reference'] ?? ''), 2400)
                    .$this->fcValueCell((string) ($item['objet'] ?? ''), 4800)
                    .$this->fcValueCell((string) ($item['pieces'] ?? '1'), 1000)
                    .$this->fcValueCell((string) ($item['observations'] ?? ''), 1466)
                    .'</w:tr>';
            }
        }

        $obs = trim((string) ($data['observations'] ?? ''));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    '.$this->fcHeaderTable($data).'
    '.$this->fcSpacer(80).'
    '.$this->fcTitleBand('BORDEREAU DE TRANSMISSION', 'INTERNE', '0B6B3A').'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('IDENTIFICATION').'
    '.$this->fcBorderedTable($metaXml).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('DOCUMENTS TRANSMIS').'
    '.$this->fcBorderedTable($itemRows).'
    '.($obs !== '' ? $this->fcSpacer(120).$this->fcSectionTitle('OBSERVATIONS').$this->fcObjetBox($obs) : '').'
    '.$this->fcSpacer(160).'
    '.$this->fcDualSignatures('Le remettant', 'Le destinataire (accusé)').'
    '.$this->fcSectPr().'
  </w:body>
</w:document>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildDispatchSlipDocumentXml(array $data): string
    {
        $metaPairs = [
            ['N° Bordereau', $data['numero_bordereau'] ?? '', 'Date d\'envoi', $data['date_envoi'] ?? ''],
            ['N° Départ', $data['numero_depart'] ?? '', 'Référence', $data['reference_externe'] ?? ''],
            ['Canal / Mode', $data['mode_envoi'] ?? '', 'N° suivi', $data['numero_suivi'] ?? ''],
            ['Expéditeur', $data['expediteur'] ?? '', 'Pièces', (string) ($data['nombre_pieces'] ?? '0')],
        ];

        $metaXml = '';
        foreach ($metaPairs as [$l1, $v1, $l2, $v2]) {
            $metaXml .= '<w:tr>'
                .$this->fcLabelCell($l1, 2100)
                .$this->fcValueCell((string) $v1, 2900)
                .$this->fcLabelCell($l2, 2100)
                .$this->fcValueCell((string) $v2, 2900)
                .'</w:tr>';
        }

        $ampliations = trim((string) ($data['ampliations'] ?? ''));
        $obs = trim((string) ($data['observations'] ?? ''));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    '.$this->fcHeaderTable($data).'
    '.$this->fcSpacer(80).'
    '.$this->fcTitleBand('BORDEREAU D\'ENVOI', strtoupper((string) ($data['confidentialite_label'] ?? 'ORDINAIRE')), '0B6B3A').'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('EXPÉDITION').'
    '.$this->fcBorderedTable($metaXml).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('DESTINATAIRE(S)').'
    '.$this->fcObjetBox((string) ($data['destinataires'] ?? '—')).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('OBJET').'
    '.$this->fcObjetBox((string) ($data['objet'] ?? '')).'
    '.($ampliations !== '' ? $this->fcSpacer(120).$this->fcSectionTitle('AMPLIATIONS / COPIES').$this->fcObjetBox($ampliations) : '').'
    '.($obs !== '' ? $this->fcSpacer(120).$this->fcSectionTitle('OBSERVATIONS').$this->fcObjetBox($obs) : '').'
    '.$this->fcSpacer(160).'
    '.$this->fcDualSignatures('Le service expéditeur', 'Le destinataire (pour réception)').'
    '.$this->fcSectPr().'
  </w:body>
</w:document>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildAcknowledgementDocumentXml(array $data): string
    {
        $metaPairs = [
            ['N° Accusé', $data['numero_accuse'] ?? '', 'Date d\'accusé', $data['date_accuse'] ?? ''],
            ['N° Enregistrement', $data['numero_enregistrement'] ?? '', 'Date courrier', $data['date_correspondance'] ?? ''],
            ['Mode', $data['mode_accuse'] ?? '', 'Signataire', $data['signataire'] ?? ''],
        ];

        $metaXml = '';
        foreach ($metaPairs as [$l1, $v1, $l2, $v2]) {
            $metaXml .= '<w:tr>'
                .$this->fcLabelCell($l1, 2100)
                .$this->fcValueCell((string) $v1, 2900)
                .$this->fcLabelCell($l2, 2100)
                .$this->fcValueCell((string) $v2, 2900)
                .'</w:tr>';
        }

        $obs = trim((string) ($data['observations'] ?? ''));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    '.$this->fcHeaderTable($data).'
    '.$this->fcSpacer(80).'
    '.$this->fcTitleBand('ACCUSÉ DE RÉCEPTION', 'COURRIER', '0B6B3A').'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('RÉFÉRENCES').'
    '.$this->fcBorderedTable($metaXml).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('EXPÉDITEUR').'
    '.$this->fcObjetBox((string) ($data['expediteur'] ?? '—')).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('OBJET DU COURRIER').'
    '.$this->fcObjetBox((string) ($data['objet'] ?? '')).'
    '.$this->fcSpacer(120).'
    '.$this->fcSectionTitle('MENTION').'
    '.$this->fcObjetBox("Le soussigné accuse réception du courrier ci-dessus référencé.").'
    '.($obs !== '' ? $this->fcSpacer(120).$this->fcSectionTitle('OBSERVATIONS').$this->fcObjetBox($obs) : '').'
    '.$this->fcSpacer(160).'
    '.$this->fcDualSignatures('Pour la structure destinataire', 'Cachet / Signature').'
    '.$this->fcSectPr().'
  </w:body>
</w:document>';
    }

    private function fcSectPr(): string
    {
        return '<w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360"/>
    </w:sectPr>';
    }

    private function fcDualSignatures(string $leftRole, string $rightRole): string
    {
        $sig = fn (string $role) => $this->wParagraph($role, true, 'center', 18)
            .$this->wParagraph('(Signature et cachet)', false, 'center', 14)
            .$this->wParagraph(' ', false)
            .$this->wParagraph(' ', false)
            .$this->wParagraph(' ', false)
            .$this->wParagraph(' ', false)
            .$this->wParagraph('……………………………………', false, 'center', 16);

        return '<w:tbl>
          <w:tblPr>
            <w:tblW w:w="10466" w:type="dxa"/>
            '.$this->fcTblBorders('0B6B3A', 6).'
          </w:tblPr>
          <w:tr>
            <w:tc>
              <w:tcPr>
                <w:tcW w:w="5233" w:type="dxa"/>
                <w:tcMar><w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/></w:tcMar>
              </w:tcPr>
              '.$sig($leftRole).'
            </w:tc>
            <w:tc>
              <w:tcPr>
                <w:tcW w:w="5233" w:type="dxa"/>
                <w:tcMar><w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/></w:tcMar>
              </w:tcPr>
              '.$sig($rightRole).'
            </w:tc>
          </w:tr>
        </w:tbl>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fcHeaderTable(array $data): string
    {
        $left = $this->wParagraph('MINISTÈRE DE L\'ÉCONOMIE', true, 'left', 16)
            .$this->wParagraph('ET DES FINANCES', true, 'left', 16)
            .$this->wParagraph('————————————', false, 'left', 14)
            .$this->wParagraph('Direction Générale du Trésor', false, 'left', 15)
            .$this->wParagraph('et de la Comptabilité Publique', false, 'left', 15);

        $right = $this->wParagraph('RÉPUBLIQUE DU MALI', true, 'right', 18)
            .$this->wParagraph('Un Peuple – Un But – Une Foi', false, 'right', 15)
            .$this->wParagraph(' ', false, 'right', 10)
            .$this->wParagraph('Saisi par : '.((string) ($data['saisi_par'] ?? '—')), false, 'right', 16);

        return '<w:tbl>
          <w:tblPr>
            <w:tblW w:w="10466" w:type="dxa"/>
            <w:tblBorders>
              <w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/>
              <w:insideH w:val="nil"/><w:insideV w:val="nil"/>
            </w:tblBorders>
          </w:tblPr>
          <w:tr>
            <w:tc><w:tcPr><w:tcW w:w="5233" w:type="dxa"/></w:tcPr>'.$left.'</w:tc>
            <w:tc><w:tcPr><w:tcW w:w="5233" w:type="dxa"/></w:tcPr>'.$right.'</w:tc>
          </w:tr>
        </w:tbl>';
    }

    private function fcTitleBand(string $title, string $conf, string $color): string
    {
        return '<w:tbl>
          <w:tblPr>
            <w:tblW w:w="10466" w:type="dxa"/>
            <w:tblBorders>
              <w:top w:val="single" w:sz="18" w:color="'.$color.'"/>
              <w:left w:val="single" w:sz="18" w:color="'.$color.'"/>
              <w:bottom w:val="single" w:sz="18" w:color="'.$color.'"/>
              <w:right w:val="single" w:sz="18" w:color="'.$color.'"/>
              <w:insideH w:val="nil"/><w:insideV w:val="nil"/>
            </w:tblBorders>
          </w:tblPr>
          <w:tr>
            <w:tc>
              <w:tcPr>
                <w:tcW w:w="10466" w:type="dxa"/>
                <w:shd w:val="clear" w:color="auto" w:fill="F3F8F5"/>
                <w:tcMar>
                  <w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/>
                </w:tcMar>
              </w:tcPr>
              '.$this->wParagraph($title, true, 'center', 28).'
              '.$this->wParagraph('« '.$conf.' »', true, 'center', 22).'
            </w:tc>
          </w:tr>
        </w:tbl>';
    }

    private function fcSectionTitle(string $title): string
    {
        return '<w:tbl>
          <w:tblPr><w:tblW w:w="10466" w:type="dxa"/></w:tblPr>
          <w:tr>
            <w:tc>
              <w:tcPr>
                <w:tcW w:w="10466" w:type="dxa"/>
                <w:shd w:val="clear" w:color="auto" w:fill="0B6B3A"/>
                <w:tcMar>
                  <w:top w:w="40" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="40" w:type="dxa"/>
                </w:tcMar>
              </w:tcPr>
              <w:p>
                <w:pPr><w:jc w:val="left"/><w:spacing w:before="40" w:after="40"/></w:pPr>
                <w:r>
                  <w:rPr>
                    <w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/>
                    <w:b/><w:color w:val="FFFFFF"/><w:sz w:val="18"/>
                  </w:rPr>
                  <w:t>'.$this->escapeXml($title).'</w:t>
                </w:r>
              </w:p>
            </w:tc>
          </w:tr>
        </w:tbl>'.$this->fcSpacer(60);
    }

    private function fcObjetBox(string $objet): string
    {
        $text = $objet !== '' ? $objet : '……………………………………………………………………………………';

        return '<w:tbl>
          <w:tblPr>
            <w:tblW w:w="10466" w:type="dxa"/>
            '.$this->fcTblBorders('0B6B3A', 8).'
          </w:tblPr>
          <w:tr>
            <w:tc>
              <w:tcPr>
                <w:tcW w:w="10466" w:type="dxa"/>
                <w:shd w:val="clear" w:color="auto" w:fill="FFFEF5"/>
                <w:tcMar>
                  <w:top w:w="100" w:type="dxa"/><w:left w:w="120" w:type="dxa"/>
                  <w:bottom w:w="100" w:type="dxa"/><w:right w:w="120" w:type="dxa"/>
                </w:tcMar>
              </w:tcPr>
              '.$this->wParagraph($text, true, 'left', 21).'
            </w:tc>
          </w:tr>
        </w:tbl>';
    }

    private function fcSignaturesTable(): string
    {
        $sig = fn (string $role) => $this->wParagraph($role, true, 'center', 18)
            .$this->wParagraph('(Signature et cachet)', false, 'center', 14)
            .$this->wParagraph(' ', false)
            .$this->wParagraph(' ', false)
            .$this->wParagraph(' ', false)
            .$this->wParagraph(' ', false)
            .$this->wParagraph('……………………………………', false, 'center', 16);

        return '<w:tbl>
          <w:tblPr>
            <w:tblW w:w="10466" w:type="dxa"/>
            '.$this->fcTblBorders('0B6B3A', 6).'
          </w:tblPr>
          <w:tr>
            <w:tc>
              <w:tcPr>
                <w:tcW w:w="5233" w:type="dxa"/>
                <w:tcMar><w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/></w:tcMar>
              </w:tcPr>
              '.$sig('Le Directeur Général').'
            </w:tc>
            <w:tc>
              <w:tcPr>
                <w:tcW w:w="5233" w:type="dxa"/>
                <w:tcMar><w:top w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/></w:tcMar>
              </w:tcPr>
              '.$sig('Le Directeur Général Adjoint').'
            </w:tc>
          </w:tr>
        </w:tbl>';
    }

    private function fcBorderedTable(string $rowsXml): string
    {
        return '<w:tbl>
          <w:tblPr>
            <w:tblW w:w="10466" w:type="dxa"/>
            '.$this->fcTblBorders('444444', 6).'
          </w:tblPr>
          '.$rowsXml.'
        </w:tbl>';
    }

    private function fcTblBorders(string $color, int $sz): string
    {
        return '<w:tblBorders>
          <w:top w:val="single" w:sz="'.$sz.'" w:color="'.$color.'"/>
          <w:left w:val="single" w:sz="'.$sz.'" w:color="'.$color.'"/>
          <w:bottom w:val="single" w:sz="'.$sz.'" w:color="'.$color.'"/>
          <w:right w:val="single" w:sz="'.$sz.'" w:color="'.$color.'"/>
          <w:insideH w:val="single" w:sz="4" w:color="'.$color.'"/>
          <w:insideV w:val="single" w:sz="4" w:color="'.$color.'"/>
        </w:tblBorders>';
    }

    private function fcLabelCell(string $text, int $width): string
    {
        return '<w:tc>
          <w:tcPr>
            <w:tcW w:w="'.$width.'" w:type="dxa"/>
            <w:shd w:val="clear" w:color="auto" w:fill="E8F5EE"/>
            <w:tcMar>
              <w:top w:w="40" w:type="dxa"/><w:left w:w="60" w:type="dxa"/>
              <w:bottom w:w="40" w:type="dxa"/><w:right w:w="60" w:type="dxa"/>
            </w:tcMar>
          </w:tcPr>
          '.$this->wParagraph($text, true, 'left', 16).'
        </w:tc>';
    }

    private function fcValueCell(string $text, int $width, bool $bold = false): string
    {
        return '<w:tc>
          <w:tcPr>
            <w:tcW w:w="'.$width.'" w:type="dxa"/>
            <w:tcMar>
              <w:top w:w="40" w:type="dxa"/><w:left w:w="60" w:type="dxa"/>
              <w:bottom w:w="40" w:type="dxa"/><w:right w:w="60" w:type="dxa"/>
            </w:tcMar>
          </w:tcPr>
          '.$this->wParagraph($text !== '' ? $text : '—', $bold, 'left', 17).'
        </w:tc>';
    }

    private function fcSpacer(int $twips): string
    {
        return '<w:p><w:pPr><w:spacing w:before="0" w:after="'.$twips.'"/></w:pPr></w:p>';
    }

    /**
     * @param  list<array{code?: string, label?: string, checked?: bool}>  $items
     */
    private function buildCheckboxGridXml(array $items, int $columns = 3, bool $showCode = false): string
    {
        if ($items === []) {
            return $this->wParagraph('—');
        }

        $rows = array_chunk($items, $columns);
        $cellWidth = (int) floor(10466 / $columns);
        $xml = '<w:tbl>
          <w:tblPr>
            <w:tblW w:w="10466" w:type="dxa"/>
            '.$this->fcTblBorders('888888', 4).'
          </w:tblPr>';

        foreach ($rows as $row) {
            $xml .= '<w:tr>';
            for ($i = 0; $i < $columns; $i++) {
                $item = $row[$i] ?? null;
                if (! $item) {
                    $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.$cellWidth.'" w:type="dxa"/></w:tcPr>'.$this->wParagraph(' ').'</w:tc>';
                    continue;
                }

                $checked = ! empty($item['checked']);
                $markAscii = $checked ? '[X]' : '[ ]';
                $label = $showCode
                    ? trim(($item['code'] ?? '').' — '.($item['label'] ?? ''))
                    : trim((string) ($item['label'] ?? $item['code'] ?? ''));
                $fill = $checked ? 'E8F5EE' : 'FFFFFF';

                $xml .= '<w:tc>
                  <w:tcPr>
                    <w:tcW w:w="'.$cellWidth.'" w:type="dxa"/>
                    <w:shd w:val="clear" w:color="auto" w:fill="'.$fill.'"/>
                    <w:tcMar>
                      <w:top w:w="30" w:type="dxa"/><w:left w:w="50" w:type="dxa"/>
                      <w:bottom w:w="30" w:type="dxa"/><w:right w:w="50" w:type="dxa"/>
                    </w:tcMar>
                  </w:tcPr>
                  '.$this->wParagraph($markAscii.' '.$label, $checked, 'left', 15).'
                </w:tc>';
            }
            $xml .= '</w:tr>';
        }

        $xml .= '</w:tbl>';

        return $xml;
    }

    private function wParagraph(string $text, bool $bold = false, string $align = 'left', int $size = 20): string
    {
        $boldXml = $bold ? '<w:b/>' : '';

        return '<w:p>
          <w:pPr>
            <w:jc w:val="'.$align.'"/>
            <w:spacing w:before="20" w:after="20"/>
          </w:pPr>
          <w:r>
            <w:rPr>
              <w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman"/>
              '.$boldXml.'
              <w:sz w:val="'.$size.'"/>
              <w:szCs w:val="'.$size.'"/>
            </w:rPr>
            <w:t xml:space="preserve">'.$this->escapeXml($text).'</w:t>
          </w:r>
        </w:p>';
    }

    private function wCell(string $text, bool $bold = false, int $width = 2500, string $align = 'left'): string
    {
        $lines = preg_split("/\n/", $text) ?: [$text];
        $paras = '';
        foreach ($lines as $line) {
            $paras .= $this->wParagraph($line, $bold, $align, 18);
        }

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/></w:tcPr>'.$paras.'</w:tc>';
    }
}
