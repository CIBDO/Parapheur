<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\MeetingParticipant;
use App\Models\MeetingTemplate;
use App\Models\User;

class MeetingDocumentRenderer
{
    public function convocation(Meeting $meeting, ?User $signatory = null): string
    {
        $meeting->loadMissing(['type', 'chair', 'secretary', 'structure', 'agendaItems.presenter', 'participants.user']);

        $placeholders = $this->placeholders($meeting, $signatory);

        return $this->renderKind('convocation', $placeholders, $this->defaultConvocation($placeholders));
    }

    public function attendanceList(Meeting $meeting): string
    {
        $meeting->loadMissing(['participants.user', 'chair', 'structure', 'type', 'secretary', 'agendaItems']);
        $rows = '';
        foreach ($meeting->participants as $participant) {
            $rows .= '<tr><td>'.e($participant->displayName()).'</td><td>'.e($participant->user?->structure?->name ?: $participant->external_structure ?: '—').'</td><td>'.e($participant->attendance_status ?: '—').'</td><td>'.e($participant->representative_name ?: $participant->representative?->name ?: '—').'</td></tr>';
        }

        $fallback = $this->wrap('Liste de présence', $meeting, <<<HTML
<table>
  <thead><tr><th>Participant</th><th>Structure</th><th>Présence</th><th>Représentation</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
HTML);

        return $this->renderKind('attendance', $this->placeholders($meeting), $fallback);
    }

    public function agenda(Meeting $meeting): string
    {
        $meeting->loadMissing(['agendaItems.presenter', 'type', 'chair', 'secretary', 'structure', 'participants.user']);
        $rows = '';
        foreach ($meeting->agendaItems as $item) {
            $rows .= '<tr><td>'.e($item->item_number).'</td><td>'.e($item->title).'</td><td>'.e($item->presenter?->name ?: '—').'</td><td>'.e($item->duration_minutes ? $item->duration_minutes.' min' : '—').'</td></tr>';
        }

        $fallback = $this->wrap('Ordre du jour', $meeting, <<<HTML
<table>
  <thead><tr><th>N°</th><th>Point</th><th>Présentateur</th><th>Durée</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
HTML);

        return $this->renderKind('agenda', $this->placeholders($meeting), $fallback);
    }

    public function minutes(Meeting $meeting, MeetingMinute $minute): string
    {
        return $minute->body ?: $this->buildMinutesBody($meeting, $minute->kind);
    }

    public function decisionsExtract(Meeting $meeting): string
    {
        $meeting->loadMissing(['decisions.assignee', 'decisions.structure', 'type', 'chair', 'secretary', 'structure', 'participants.user', 'agendaItems']);
        $rows = '';
        foreach ($meeting->decisions as $decision) {
            $rows .= '<tr><td>'.e($decision->reference ?: '#'.$decision->id).'</td><td>'.e($decision->title).'</td><td>'.e($decision->assignee?->name ?: '—').'</td><td>'.e(optional($decision->due_date)->format('d/m/Y') ?: '—').'</td><td>'.e($decision->effectiveStatus()->label()).'</td></tr>';
        }

        $fallback = $this->wrap('Relevé de décisions', $meeting, <<<HTML
<table>
  <thead><tr><th>Réf.</th><th>Décision</th><th>Responsable</th><th>Échéance</th><th>Statut</th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
HTML);

        return $this->renderKind('decisions', $this->placeholders($meeting), $fallback);
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

        $fallback = $this->wrap($title, $meeting, $intro.$agendaBlocks.<<<HTML
<h2>Synthèse des décisions</h2>
<table>
  <thead><tr><th>Réf.</th><th>Décision</th><th>Responsable</th><th>Échéance</th></tr></thead>
  <tbody>{$decisionRows}</tbody>
</table>
HTML);

        return $this->renderKind($kind, $this->placeholders($meeting), $fallback);
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

    /**
     * @param  array<string, string>  $p
     */
    private function defaultConvocation(array $p): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Convocation {$p['reference']}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; color: #14261A; margin: 32px; }
  .header { border-bottom: 3px solid #0B6B3A; padding-bottom: 12px; margin-bottom: 24px; }
  .gold { color: #C9A227; }
  h1 { color: #0B6B3A; font-size: 20px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 4px 0; vertical-align: top; }
</style></head>
<body>
  <div class="header">
    <strong>RÉPUBLIQUE DE CÔTE D'IVOIRE</strong><br>
    Union – Discipline – Travail<br>
    <span class="gold">Direction Générale du Trésor et de la Comptabilité Publique</span>
  </div>
  <h1>CONVOCATION</h1>
  <p>Référence : {$this->e($p['reference'])}</p>
  <table>
    <tr><td width="160"><strong>Objet</strong></td><td>{$this->e($p['object'])}</td></tr>
    <tr><td><strong>Date</strong></td><td>{$this->e($p['date'])}</td></tr>
    <tr><td><strong>Heure</strong></td><td>{$this->e($p['time'])} — {$this->e($p['end_time'])}</td></tr>
    <tr><td><strong>Lieu</strong></td><td>{$this->e($p['location'])}</td></tr>
    <tr><td><strong>Président</strong></td><td>{$this->e($p['chair'])}</td></tr>
    <tr><td><strong>Destinataires</strong></td><td>{$this->e($p['participants'])}</td></tr>
  </table>
  <h2>Ordre du jour</h2>
  <p>{$p['agenda']}</p>
  <p>{$this->e($p['observations'])}</p>
  <p style="margin-top:48px">L'autorité de convocation<br><strong>{$this->e($p['signatory'])}</strong></p>
</body></html>
HTML;
    }

    private function wrap(string $title, Meeting $meeting, string $inner): string
    {
        $ref = e($meeting->reference ?: '');
        $date = e(optional($meeting->meeting_date)->format('d/m/Y') ?: '');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>{$this->e($title)}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; color: #14261A; margin: 32px; }
  .header { border-bottom: 3px solid #0B6B3A; padding-bottom: 12px; margin-bottom: 24px; }
  h1, h2, h3 { color: #0B6B3A; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
  th { background: #E8F5EE; }
</style></head>
<body>
  <div class="header">
    <strong>DGTCP</strong> — E-Tresor<br>
    {$ref} · {$date}
  </div>
  <h1>{$this->e($title)}</h1>
  <h2>{$this->e($meeting->displayTitle())}</h2>
  {$inner}
</body></html>
HTML;
    }

    private function e(?string $value): string
    {
        return e((string) $value);
    }
}