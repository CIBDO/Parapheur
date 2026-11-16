<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

class MeetingMinutesDocxService
{
    public function build(Meeting $meeting, string $kind = 'cr_detaille'): string
    {
        $meeting->loadMissing([
            'type', 'chair', 'secretary', 'structure',
            'participants.user.structure', 'participants.representative',
            'agendaItems.presenter', 'agendaItems.notes.author', 'decisions.assignee',
        ]);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 900,
            'marginBottom' => 900,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        $this->addLetterhead($section);

        $title = match ($kind) {
            'cr_simple' => 'COMPTE RENDU',
            'pv' => 'PROCÈS-VERBAL',
            'releve_decisions' => 'RELEVÉ DE DÉCISIONS',
            default => 'COMPTE RENDU DÉTAILLÉ',
        };

        $section->addText($title, ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER, 'spaceAfter' => 60]);
        $section->addText($meeting->displayTitle(), ['size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);

        if ($kind !== 'releve_decisions') {
            $section->addText('I. Informations générales', ['bold' => true, 'size' => 12], ['spaceBefore' => 120, 'spaceAfter' => 80]);
            $this->addMetaLines($section, [
                'Référence' => (string) ($meeting->reference ?: '—'),
                'Type' => (string) ($meeting->type?->name ?: '—'),
                'Date' => optional($meeting->meeting_date)->format('d/m/Y') ?: '—',
                'Heure' => trim((string) $meeting->meeting_time.($meeting->end_time ? ' — '.$meeting->end_time : '')) ?: '—',
                'Lieu' => (string) ($meeting->location ?: '—'),
                'Président' => (string) ($meeting->chair?->name ?: '—'),
                'Secrétaire' => (string) ($meeting->secretary?->name ?: '—'),
            ]);

            $present = $meeting->participants->filter(fn (MeetingParticipant $p) => $p->attendance_status === 'present');
            $absent = $meeting->participants->filter(fn (MeetingParticipant $p) => in_array($p->attendance_status, ['absent', 'excuse', 'represente'], true));

            $section->addText('II. Participants', ['bold' => true, 'size' => 12], ['spaceBefore' => 160, 'spaceAfter' => 80]);
            $section->addText(
                $present->map(fn (MeetingParticipant $p) => $p->displayName())->implode(', ') ?: '—',
                ['size' => 11],
                ['spaceAfter' => 120]
            );

            $section->addText('III. Absents / excusés / représentés', ['bold' => true, 'size' => 12], ['spaceBefore' => 80, 'spaceAfter' => 80]);
            if ($absent->isEmpty()) {
                $section->addText('—', ['size' => 11], ['spaceAfter' => 120]);
            } else {
                foreach ($absent as $p) {
                    $label = $p->displayName();
                    if ($p->attendance_status === 'represente') {
                        $label .= ' (représenté par '.($p->representative_name ?: $p->representative?->name ?: '—').')';
                    }
                    $section->addListItem($label, 0, ['size' => 11]);
                }
            }

            $section->addText('IV. Ordre du jour et déroulement', ['bold' => true, 'size' => 12], ['spaceBefore' => 160, 'spaceAfter' => 80]);
        }

        foreach ($meeting->agendaItems as $item) {
            $section->addText(
                trim(($item->item_number ? $item->item_number.'. ' : '').$item->title),
                ['bold' => true, 'size' => 11],
                ['spaceBefore' => 120, 'spaceAfter' => 40]
            );
            $section->addText('Présenté par : '.($item->presenter?->name ?: '—'), ['size' => 10, 'italic' => true], ['spaceAfter' => 60]);

            if ($kind !== 'releve_decisions') {
                $official = $item->notes->where('visibility', 'officielle');
                $resume = $official->where('section', 'resume')->pluck('body')->implode("\n");
                $obs = $official->where('section', 'observations')->pluck('body')->implode("\n");
                $section->addText('Synthèse :', ['bold' => true, 'size' => 10], ['spaceAfter' => 20]);
                $section->addText($resume !== '' ? $resume : '—', ['size' => 11], ['spaceAfter' => 60]);
                $section->addText('Observations :', ['bold' => true, 'size' => 10], ['spaceAfter' => 20]);
                $section->addText($obs !== '' ? $obs : '—', ['size' => 11], ['spaceAfter' => 60]);
            }

            $decisions = $meeting->decisions->where('agenda_item_id', $item->id);
            $section->addText('Décisions :', ['bold' => true, 'size' => 10], ['spaceAfter' => 40]);
            if ($decisions->isEmpty()) {
                $section->addText('Aucune', ['size' => 11], ['spaceAfter' => 80]);
            } else {
                foreach ($decisions as $decision) {
                    $section->addListItem(
                        $decision->title.' — '.($decision->assignee?->name ?: 'sans responsable'),
                        0,
                        ['size' => 11]
                    );
                }
            }
        }

        $section->addText('Synthèse des décisions', ['bold' => true, 'size' => 12], ['spaceBefore' => 200, 'spaceAfter' => 100]);
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '333333',
            'cellMargin' => 50,
            'alignment' => JcTable::CENTER,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);
        $table->addRow();
        foreach (['Réf.', 'Décision', 'Responsable', 'Échéance'] as $header) {
            $table->addCell(1500)->addText($header, ['bold' => true, 'size' => 10]);
        }
        if ($meeting->decisions->isEmpty()) {
            $table->addRow();
            $table->addCell(6000, ['gridSpan' => 4])->addText('Aucune décision enregistrée.', ['size' => 10]);
        } else {
            foreach ($meeting->decisions as $decision) {
                $table->addRow();
                $table->addCell(1200)->addText((string) ($decision->reference ?: ''), ['size' => 10]);
                $table->addCell(2800)->addText((string) $decision->title, ['size' => 10]);
                $table->addCell(1600)->addText((string) ($decision->assignee?->name ?: '—'), ['size' => 10]);
                $table->addCell(1200)->addText(optional($decision->due_date)->format('d/m/Y') ?: '—', ['size' => 10]);
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cr_docx_');
        $path = $tmp.'.docx';
        @unlink($tmp);
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    /**
     * Variante HTML institutionnelle pour aperçu / repli (sans DOCX).
     */
    public function buildHtmlPreview(Meeting $meeting, string $kind, MeetingDocumentRenderer $renderer): string
    {
        return $renderer->buildMinutesBody($meeting, $kind);
    }

    private function addLetterhead($section): void
    {
        $lh = config('meetings.letterhead', []);
        $ministry1 = (string) ($lh['ministry_line_1'] ?? "MINISTÈRE DE L'ÉCONOMIE");
        $ministry2 = (string) ($lh['ministry_line_2'] ?? 'ET DES FINANCES');
        $direction1 = (string) ($lh['direction_line_1'] ?? 'DIRECTION GÉNÉRALE DU TRÉSOR');
        $direction2 = (string) ($lh['direction_line_2'] ?? 'ET DE LA COMPTABILITÉ PUBLIQUE');
        $republic = (string) ($lh['republic'] ?? 'RÉPUBLIQUE DU MALI');
        $motto = (string) ($lh['motto'] ?? 'Un Peuple - Un But - Une Foi');

        // Entête dans le corps du document (pas Header Word) → entièrement modifiable dans OnlyOffice
        $table = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMargin' => 0,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);
        $table->addRow();

        $left = $table->addCell(5200);
        $left->addText($ministry1, ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER]);
        $left->addText($ministry2, ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER]);
        $left->addText('____________________', ['size' => 8], ['alignment' => Jc::CENTER]);
        $left->addText($direction1, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER, 'spaceBefore' => 40]);
        $left->addText($direction2, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
        $left->addText('____________________', ['size' => 8], ['alignment' => Jc::CENTER]);

        $right = $table->addCell(4800);
        $right->addText($republic, ['bold' => true, 'size' => 11], ['alignment' => Jc::RIGHT]);
        $right->addText($motto, ['size' => 10], ['alignment' => Jc::RIGHT]);
        $right->addText('____________________', ['size' => 8], ['alignment' => Jc::RIGHT]);

        $section->addTextBreak(1);
    }

    /**
     * @param  array<string, string>  $rows
     */
    private function addMetaLines($section, array $rows): void
    {
        foreach ($rows as $label => $value) {
            $textrun = $section->addTextRun(['spaceAfter' => 20]);
            $textrun->addText($label.' : ', ['bold' => true, 'size' => 11]);
            $textrun->addText($value, ['size' => 11]);
        }
    }
}
