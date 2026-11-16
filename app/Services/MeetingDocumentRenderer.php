<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\MeetingParticipant;
use App\Models\MeetingTemplate;
use App\Models\User;
use Illuminate\Support\Facades\View;

class MeetingDocumentRenderer
{
    /**
     * État institutionnel PDF-ready (entête République du Mali / MEF / DGTCP).
     */
    public function institutionalState(Meeting $meeting, string $kind, ?User $signatory = null): string
    {
        return match ($kind) {
            'convocation' => $this->institutionalConvocation($meeting, $signatory),
            'agenda' => $this->institutionalAgenda($meeting),
            'attendance' => $this->institutionalAttendance($meeting),
            'decisions' => $this->institutionalDecisions($meeting),
            default => abort(404, 'Type d’état inconnu.'),
        };
    }

    public function convocation(Meeting $meeting, ?User $signatory = null): string
    {
        return $this->institutionalConvocation($meeting, $signatory);
    }

    public function attendanceList(Meeting $meeting): string
    {
        return $this->institutionalAttendance($meeting);
    }

    public function agenda(Meeting $meeting): string
    {
        return $this->institutionalAgenda($meeting);
    }

    public function minutes(Meeting $meeting, MeetingMinute $minute): string
    {
        return $minute->body ?: $this->buildMinutesBody($meeting, $minute->kind);
    }

    public function decisionsExtract(Meeting $meeting): string
    {
        return $this->institutionalDecisions($meeting);
    }

    private function institutionalConvocation(Meeting $meeting, ?User $signatory = null): string
    {
        $meeting->loadMissing(['type', 'chair', 'secretary', 'structure', 'agendaItems.presenter', 'participants.user']);
        $placeholders = $this->placeholders($meeting, $signatory);

        $agendaRows = '';
        foreach ($meeting->agendaItems as $item) {
            $agendaRows .= '<tr><td style="text-align:center">'.e((string) $item->item_number).'</td><td>'.e($item->title).'</td><td>'.e($item->presenter?->name ?: '—').'</td><td style="text-align:center">'.e($item->duration_minutes ? $item->duration_minutes.' min' : '—').'</td></tr>';
        }
        if ($agendaRows === '') {
            $agendaRows = '<tr><td colspan="4">Aucun point inscrit.</td></tr>';
        }

        $body = '<h2>Ordre du jour</h2>
<table class="data">
  <thead><tr><th>N°</th><th>Point</th><th>Présentateur</th><th>Durée</th></tr></thead>
  <tbody>'.$agendaRows.'</tbody>
</table>';
        if ($placeholders['observations'] !== '') {
            $body .= '<h2>Observations</h2><p>'.nl2br(e($placeholders['observations'])).'</p>';
        }

        return $this->renderInstitutional([
            'title' => 'Convocation',
            'subtitle' => $placeholders['object'],
            'metaRows' => [
                ['label' => 'Référence', 'value' => $placeholders['reference']],
                ['label' => 'Date', 'value' => $placeholders['date']],
                ['label' => 'Heure', 'value' => trim($placeholders['time'].($placeholders['end_time'] ? ' — '.$placeholders['end_time'] : ''))],
                ['label' => 'Lieu', 'value' => $placeholders['location'] ?: '—'],
                ['label' => 'Président', 'value' => $placeholders['chair'] ?: '—'],
                ['label' => 'Destinataires', 'value' => $placeholders['participants'] ?: '—'],
            ],
            'body' => $body,
            'signatory' => $placeholders['signatory'],
            'signatoryLabel' => "L'autorité de convocation",
            'reference' => $placeholders['reference'],
            'date' => $placeholders['date'],
        ]);
    }

    private function institutionalAgenda(Meeting $meeting): string
    {
        $meeting->loadMissing(['agendaItems.presenter', 'type', 'chair', 'secretary', 'structure', 'participants.user']);
        $rows = '';
        foreach ($meeting->agendaItems as $item) {
            $rows .= '<tr><td style="text-align:center">'.e((string) $item->item_number).'</td><td>'.e($item->title).'</td><td>'.e($item->presenter?->name ?: '—').'</td><td style="text-align:center">'.e($item->duration_minutes ? $item->duration_minutes.' min' : '—').'</td></tr>';
            if ($item->description) {
                $rows .= '<tr><td></td><td colspan="3"><em>'.nl2br(e($item->description)).'</em></td></tr>';
            }
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4">Aucun point inscrit à l’ordre du jour.</td></tr>';
        }

        return $this->renderInstitutional([
            'title' => 'Ordre du jour',
            'subtitle' => $meeting->displayTitle(),
            'metaRows' => $this->commonMeta($meeting),
            'body' => '<table class="data"><thead><tr><th>N°</th><th>Point</th><th>Présentateur</th><th>Durée</th></tr></thead><tbody>'.$rows.'</tbody></table>',
            'reference' => (string) ($meeting->reference ?: ''),
            'date' => optional($meeting->meeting_date)->format('d/m/Y') ?: '',
        ]);
    }

    private function institutionalAttendance(Meeting $meeting): string
    {
        $meeting->loadMissing(['participants.user.structure', 'chair', 'structure', 'type', 'secretary']);
        $rows = '';
        foreach ($meeting->participants as $participant) {
            $rows .= '<tr>'
                .'<td>'.e($participant->displayName()).'</td>'
                .'<td>'.e($participant->user?->structure?->name ?: $participant->external_structure ?: '—').'</td>'
                .'<td style="text-align:center">'.e($participant->attendance_status ?: '—').'</td>'
                .'<td>'.e($participant->representative_name ?: $participant->representative?->name ?: '—').'</td>'
                .'</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4">Aucun participant enregistré.</td></tr>';
        }

        return $this->renderInstitutional([
            'title' => 'Liste de présence',
            'subtitle' => $meeting->displayTitle(),
            'metaRows' => $this->commonMeta($meeting),
            'body' => '<table class="data"><thead><tr><th>Participant</th><th>Structure</th><th>Présence</th><th>Représentation</th></tr></thead><tbody>'.$rows.'</tbody></table>',
            'reference' => (string) ($meeting->reference ?: ''),
            'date' => optional($meeting->meeting_date)->format('d/m/Y') ?: '',
        ]);
    }

    private function institutionalDecisions(Meeting $meeting): string
    {
        $meeting->loadMissing(['decisions.assignee', 'decisions.structure', 'type', 'chair', 'secretary', 'structure']);
        $rows = '';
        foreach ($meeting->decisions as $decision) {
            $rows .= '<tr>'
                .'<td>'.e($decision->reference ?: '#'.$decision->id).'</td>'
                .'<td>'.e($decision->title).'</td>'
                .'<td>'.e($decision->assignee?->name ?: '—').'</td>'
                .'<td style="text-align:center">'.e(optional($decision->due_date)->format('d/m/Y') ?: '—').'</td>'
                .'<td style="text-align:center">'.e($decision->effectiveStatus()->label()).'</td>'
                .'</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5">Aucune décision enregistrée.</td></tr>';
        }

        return $this->renderInstitutional([
            'title' => 'Relevé de décisions',
            'subtitle' => $meeting->displayTitle(),
            'metaRows' => $this->commonMeta($meeting),
            'body' => '<table class="data"><thead><tr><th>Réf.</th><th>Décision</th><th>Responsable</th><th>Échéance</th><th>Statut</th></tr></thead><tbody>'.$rows.'</tbody></table>',
            'reference' => (string) ($meeting->reference ?: ''),
            'date' => optional($meeting->meeting_date)->format('d/m/Y') ?: '',
        ]);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function commonMeta(Meeting $meeting): array
    {
        return [
            ['label' => 'Référence', 'value' => (string) ($meeting->reference ?: '—')],
            ['label' => 'Type', 'value' => (string) ($meeting->type?->name ?: '—')],
            ['label' => 'Date', 'value' => optional($meeting->meeting_date)->format('d/m/Y') ?: '—'],
            ['label' => 'Heure', 'value' => trim((string) $meeting->meeting_time.($meeting->end_time ? ' — '.$meeting->end_time : '')) ?: '—'],
            ['label' => 'Lieu', 'value' => (string) ($meeting->location ?: '—')],
            ['label' => 'Président', 'value' => (string) ($meeting->chair?->name ?: '—')],
            ['label' => 'Secrétaire', 'value' => (string) ($meeting->secretary?->name ?: '—')],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderInstitutional(array $data): string
    {
        return View::make('meetings.institutional-state', $data)->render();
    }

    public function buildMinutesBody(Meeting $meeting, string $kind = 'cr_detaille'): string
    {
        $meeting->loadMissing([
            'type', 'chair', 'secretary', 'structure', 'participants.user',
            'agendaItems.presenter', 'agendaItems.notes.author', 'decisions.assignee',
        ]);

        $participants = $meeting->participants->filter(fn (MeetingParticipant $p) => $p->attendance_status === 'present');
        $absents = $meeting->participants->filter(fn (MeetingParticipant $p) => in_array($p->attendance_status, ['absent', 'excuse', 'represente'], true));

        $participantList = $participants->map(fn (MeetingParticipant $p) => e($p->displayName()))->implode(', ') ?: '—';
        $absentList = $absents->map(function (MeetingParticipant $p) {
            $label = e($p->displayName());
            if ($p->attendance_status === 'represente') {
                $label .= ' (représenté par '.e($p->representative_name ?: $p->representative?->name ?: '—').')';
            }

            return $label;
        })->implode('<br>') ?: '—';

        $agendaBlocks = '';
        foreach ($meeting->agendaItems as $item) {
            $official = $item->notes->where('visibility', 'officielle');
            $resume = $official->where('section', 'resume')->pluck('body')->implode("\n");
            $obs = $official->where('section', 'observations')->pluck('body')->implode("\n");
            $decisions = $meeting->decisions->where('agenda_item_id', $item->id);
            $decisionHtml = $decisions->map(fn ($d) => '<li>'.e($d->title).' — '.e($d->assignee?->name ?: 'sans responsable').'</li>')->implode('');

            $agendaBlocks .= '<h3>'.e($item->item_number.'. '.$item->title).'</h3>';
            $agendaBlocks .= '<p><strong>Présenté par :</strong> '.e($item->presenter?->name ?: '—').'</p>';
            if ($kind !== 'releve_decisions') {
                $agendaBlocks .= '<p><strong>Synthèse :</strong><br>'.nl2br(e($resume ?: '—')).'</p>';
                $agendaBlocks .= '<p><strong>Observations :</strong><br>'.nl2br(e($obs ?: '—')).'</p>';
            }
            $agendaBlocks .= '<p><strong>Décisions :</strong></p><ul>'.($decisionHtml ?: '<li>Aucune</li>').'</ul>';
        }

        $decisionRows = '';
        foreach ($meeting->decisions as $decision) {
            $decisionRows .= '<tr><td>'.e($decision->reference ?: '').'</td><td>'.e($decision->title).'</td><td>'.e($decision->assignee?->name ?: '—').'</td><td>'.e(optional($decision->due_date)->format('d/m/Y') ?: '—').'</td></tr>';
        }

        $title = match ($kind) {
            'cr_simple' => 'Compte rendu',
            'pv' => 'Procès-verbal',
            'releve_decisions' => 'Relevé de décisions',
            default => 'Compte rendu détaillé',
        };

        $intro = $kind === 'releve_decisions' ? '' : <<<HTML
<h2>I. Informations générales</h2>
<p>Réunion : {$this->e($meeting->displayTitle())}<br>
Type : {$this->e($meeting->type?->name)}<br>
Date : {$this->e(optional($meeting->meeting_date)->format('d/m/Y'))} {$this->e($meeting->meeting_time)}<br>
Lieu : {$this->e($meeting->location)}<br>
Président : {$this->e($meeting->chair?->name)}<br>
Secrétaire : {$this->e($meeting->secretary?->name)}</p>
<h2>II. Participants</h2>
<p>{$participantList}</p>
<h2>III. Absents / excusés / représentés</h2>
<p>{$absentList}</p>
<h2>IV. Ordre du jour et déroulement</h2>
HTML;

        $body = $intro.$agendaBlocks.<<<HTML
<h2>Synthèse des décisions</h2>
<table class="data">
  <thead><tr><th>Réf.</th><th>Décision</th><th>Responsable</th><th>Échéance</th></tr></thead>
  <tbody>{$decisionRows}</tbody>
</table>
HTML;

        // Fragment éditable (entête incluse) — modifiable dans Tiptap / puis figé dans le DOCX OnlyOffice
        $editorBody = $this->letterheadEditableHtml()
            .'<h1 style="text-align:center;text-transform:uppercase;">'.$this->e($title).'</h1>'
            .'<p style="text-align:center;">'.$this->e($meeting->displayTitle()).'</p>'
            .$body;

        return $this->renderKind($kind, $this->placeholders($meeting), $editorBody);
    }

    /**
     * Entête institutionnelle sous forme de HTML éditable (Tiptap / aperçu).
     */
    public function letterheadEditableHtml(): string
    {
        $lh = config('meetings.letterhead', []);
        $ministry1 = e((string) ($lh['ministry_line_1'] ?? "MINISTÈRE DE L'ÉCONOMIE"));
        $ministry2 = e((string) ($lh['ministry_line_2'] ?? 'ET DES FINANCES'));
        $direction1 = e((string) ($lh['direction_line_1'] ?? 'DIRECTION GÉNÉRALE DU TRÉSOR'));
        $direction2 = e((string) ($lh['direction_line_2'] ?? 'ET DE LA COMPTABILITÉ PUBLIQUE'));
        $republic = e((string) ($lh['republic'] ?? 'RÉPUBLIQUE DU MALI'));
        $motto = e((string) ($lh['motto'] ?? 'Un Peuple - Un But - Une Foi'));

        // Paragraphes (pas de <table>/<div>) pour rester éditable dans Tiptap StarterKit
        return <<<HTML
<p class="minutes-letterhead-block" style="text-align:center;text-transform:uppercase;"><strong>{$ministry1}</strong><br><strong>{$ministry2}</strong></p>
<p style="text-align:center;">______________</p>
<p style="text-align:center;text-transform:uppercase;"><strong>{$direction1}</strong><br><strong>{$direction2}</strong></p>
<p style="text-align:center;">______________</p>
<p style="text-align:right;text-transform:uppercase;"><strong>{$republic}</strong><br><em style="text-transform:none;font-style:normal;">{$motto}</em></p>
<p style="text-align:right;">______________</p>
HTML;
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function renderKind(string $kind, array $placeholders, string $fallback): string
    {
        $template = MeetingTemplate::query()
            ->where('kind', $kind)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if (! $template) {
            return $fallback;
        }

        $body = $template->body;
        foreach ($placeholders as $key => $value) {
            $body = str_replace('{{'.$key.'}}', $value, $body);
        }

        return $body;
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(Meeting $meeting, ?User $signatory = null): array
    {
        $participants = $meeting->participants
            ->map(fn (MeetingParticipant $p) => $p->displayName())
            ->implode(', ');

        $agenda = $meeting->agendaItems
            ->map(fn ($item) => $item->item_number.'. '.$item->title)
            ->implode("\n");

        return [
            'reference' => (string) ($meeting->reference ?: ''),
            'title' => $meeting->displayTitle(),
            'object' => (string) ($meeting->object ?: $meeting->title),
            'date' => optional($meeting->meeting_date)->format('d/m/Y') ?: '',
            'time' => (string) $meeting->meeting_time,
            'end_time' => (string) $meeting->end_time,
            'location' => (string) ($meeting->location ?: ''),
            'visio_url' => (string) ($meeting->visio_url ?: ''),
            'chair' => (string) ($meeting->chair?->name ?: ''),
            'secretary' => (string) ($meeting->secretary?->name ?: ''),
            'structure' => (string) ($meeting->structure?->name ?: 'DGTCP'),
            'participants' => $participants,
            'agenda' => nl2br(e($agenda)),
            'observations' => (string) ($meeting->observations ?: ''),
            'signatory' => (string) ($signatory?->name ?: $meeting->chair?->name ?: ''),
        ];
    }

    private function e(?string $value): string
    {
        return e((string) $value);
    }
}