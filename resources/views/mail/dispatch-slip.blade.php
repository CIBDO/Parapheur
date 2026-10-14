<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <style>
    @php
      $fontDir = str_replace('\\', '/', resource_path('fonts/mail'));
    @endphp
    @font-face { font-family: 'Times New Roman'; font-style: normal; font-weight: normal; src: url('{{ $fontDir }}/times.ttf') format('truetype'); }
    @font-face { font-family: 'Times New Roman'; font-style: normal; font-weight: bold; src: url('{{ $fontDir }}/timesbd.ttf') format('truetype'); }
    @font-face { font-family: 'Times New Roman'; font-style: italic; font-weight: normal; src: url('{{ $fontDir }}/timesi.ttf') format('truetype'); }
    @font-face { font-family: 'Times New Roman'; font-style: italic; font-weight: bold; src: url('{{ $fontDir }}/timesbi.ttf') format('truetype'); }
    @font-face { font-family: 'Kunstler Script'; font-style: normal; font-weight: normal; src: url('{{ $fontDir }}/KUNSTLER.TTF') format('truetype'); }
    @page { margin: 2.5cm; }
    body { font-family: 'Times New Roman', serif; color: #111; font-size: 12pt; }
    table.banner { width: 100%; border-collapse: collapse; }
    table.banner td { border: none; vertical-align: top; }
    .head-block { text-align: left; margin-bottom: 8px; }
    .ministry, .direction { display: inline-block; font-family: 'Times New Roman', serif; font-weight: bold; font-size: 12pt; text-align: center; text-transform: uppercase; line-height: 1.15; }
    .rule { width: 68%; border-bottom: 1px solid #000; margin: 3px auto 0; height: 0; }
    .republic { font-family: 'Times New Roman', serif; font-weight: bold; font-size: 13pt; text-align: right; text-transform: uppercase; }
    .motto { font-family: 'Times New Roman', serif; text-align: right; font-size: 11pt; margin-top: 3px; }
    .date-line { text-align: right; font-family: 'Kunstler Script', cursive; font-size: 24pt; margin: 18px 0 0; line-height: 1; }
    table.intro { width: 100%; border-collapse: collapse; margin-top: 2px; }
    table.intro td { border: none; vertical-align: middle; }
    .sender { text-align: center; font-family: 'Kunstler Script', cursive; font-size: 24pt; line-height: 1.05; margin: 0 8px; }
    .a { text-align: center; font-family: 'Kunstler Script', cursive; font-size: 24pt; margin: 6px 0 0; line-height: 1; }
    .recipient { text-align: center; font-family: 'Times New Roman', serif; font-size: 13pt; margin: 4px 16px 0; }
    .city { text-align: center; font-family: 'Times New Roman', serif; font-weight: bold; text-transform: uppercase; margin-top: 8px; font-size: 13pt; }
    table.slip-title { width: 100%; border-collapse: separate; border-spacing: 0; margin: 14px 0 8px; }
    table.slip-title td { font-family: 'Times New Roman', serif; font-weight: bold; font-size: 22pt; border: none; padding: 0; vertical-align: bottom; white-space: nowrap; }
    td.title-gap { width: 100%; border-bottom: 1.2pt solid #000; }
    .frame { border: 2.5pt solid #000; width: 100%; }
    table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.grid th, table.grid td { border: 1px solid #000; padding: 7px 8px; font-family: 'Times New Roman', serif; font-size: 12pt; vertical-align: middle; word-wrap: break-word; }
    table.grid th { text-align: center; font-weight: bold; height: 26px; }
    td.qty, th.qty { text-align: center; width: 16%; }
    td.obs, th.obs { width: 30%; text-align: center; }
    td.designation { text-align: left; height: 28px; }
    tr.total td { font-weight: bold; text-align: center; height: 30px; }
    table.sign { width: 100%; border-collapse: collapse; margin-top: 26px; }
    table.sign td { border: none; text-align: right; padding: 0 8mm 0 0; font-family: 'Times New Roman', serif; }
    .sign-role { font-weight: bold; text-transform: uppercase; font-size: 13pt; }
    .sign-name { font-weight: bold; font-size: 13pt; }
    .sign-grade { font-style: italic; font-size: 12pt; }
    .bottom { position: fixed; bottom: 1.1cm; left: 2.5cm; right: 2.5cm; text-align: center; font-family: 'Times New Roman', serif; font-size: 8pt; border-top: 1px solid #000; padding-top: 3px; line-height: 1.35; }
  </style>
</head>
<body>
  @php
    $ministryText = trim((string) ($ministry ?? "MINISTERE DE L'ECONOMIE ET DES FINANCES"));
    $directionText = trim((string) ($structureEntete ?: $structureLine ?: 'DIRECTION GENERALE DU TRESOR ET DE LA COMPTABILITE PUBLIQUE'));
    $ministryText = preg_replace('/(ECONOMIE|ÉCONOMIE)\s+(ET\b)/u', "$1\n$2", $ministryText) ?? $ministryText;
    $directionText = preg_replace('/(TRESOR|TRÉSOR)\s+(ET\b)/u', "$1\n$2", $directionText) ?? $directionText;
    $republicText = trim((string) ($republic ?? 'REPUBLIQUE DU MALI'));
    $mottoText = trim((string) ($motto ?? 'Un Peuple - Un But - Une Foi'));
    $recipient = trim((string) ($recipientTitle ?? ''));
    $code = trim((string) ($structureCode ?? ''));
    $suffix = ($code === '' || preg_match('/^DGTCP$/i', $code)) ? 'DGTCP - DG' : $code;
    $placeName = trim((string) ($place ?? '')) !== '' ? trim((string) $place) : 'Bamako';
    $formula = trim((string) ($senderTitle ?? ''));
    if ($formula === '') {
        $formula = 'Le Directeur Général du Trésor et de la Comptabilité Publique';
    }
    if (preg_match('/^(.*)\s+(Comptabilit[eé]\b.*)$/ui', $formula, $formulaMatch)) {
        $formulaLines = [trim($formulaMatch[1]), trim($formulaMatch[2])];
    } else {
        $words = preg_split('/\s+/u', $formula) ?: [$formula];
        $cut = max(1, (int) ceil(count($words) / 2));
        $formulaLines = [
            implode(' ', array_slice($words, 0, $cut)),
            implode(' ', array_slice($words, $cut)),
        ];
    }
    $contact = trim((string) ($footer ?? ''));
    if ($contact === '') {
        $contact = trim((string) ($headerExtra ?? ''));
    }
    if ($contact === '') {
        $contact = "Direction générale du Trésor et de la Comptabilité publique BP : 1887, Hamdallaye ACI 2000 (BAMAKO), Tél : 20 23 19 79/17 07 29 50, site web : www.tresor.gouv.ml";
    }
    $rows = array_values($lines ?? []);
    while (count($rows) < 4) {
        $rows[] = ['designation' => '', 'quantity' => '', 'observation' => ''];
    }
  @endphp

  <table class="banner">
    <tr>
      <td width="56%">
        <div class="head-block">
          <div class="ministry">{!! nl2br(e($ministryText)) !!}<div class="rule"></div></div>
        </div>
        <div class="head-block">
          <div class="direction">{!! nl2br(e($directionText)) !!}<div class="rule"></div></div>
        </div>
      </td>
      <td width="44%">
        <div class="republic">{{ $republicText }}</div>
        <div class="motto">{{ $mottoText }}</div>
        <div class="date-line">{{ $placeName }}, le</div>
      </td>
    </tr>
  </table>

  <table class="intro">
    <tr>
      <td width="22%" align="center">
        @if(!empty($emblem))
          <img src="{{ $emblem }}" width="96" height="96" alt="">
        @endif
      </td>
      <td width="78%">
        <div class="sender">
          {{ $formulaLines[0] }}<br>
          {{ $formulaLines[1] }}
        </div>
        <div class="a">A</div>
        <div class="recipient">{{ $recipient !== '' ? $recipient : 'Madame/Monsieur le ............' }}</div>
        <div class="city">{{ $recipientCity ?: 'BAMAKO' }}</div>
      </td>
    </tr>
  </table>

  <table class="slip-title">
    <tr>
      <td>Bordereau d'envoi N°</td>
      <td class="title-gap"><table style="width:100%; border-collapse:collapse;"><tr><td style="border-bottom:1px solid #000; font-size:14pt; line-height:1;">&nbsp;</td></tr></table></td>
      <td>/{{ $suffix }}</td>
    </tr>
  </table>

  <div class="frame">
    <table class="grid">
      <thead>
        <tr>
          <th>DESIGNATION DES PIECES</th>
          <th class="qty">NOMBRE</th>
          <th class="obs">OBSERVATIONS</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $line)
          @php
            $label = trim((string) ($line['designation'] ?? ''));
            if ($label !== '' && ! str_starts_with($label, '-')) {
                $label = '- '.$label;
            }
          @endphp
          <tr>
            <td class="designation">{{ $label }}</td>
            <td class="qty">{{ $line['quantity'] !== '' && $line['quantity'] !== null ? $line['quantity'] : '' }}</td>
            <td class="obs">{{ $line['observation'] ?? '' }}</td>
          </tr>
        @endforeach
        <tr class="total">
          <td>TOTAL</td>
          <td class="qty">{{ $total }}</td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>

  <table class="sign">
    <tr>
      <td class="sign-role">{{ $signatoryRole ?: 'LE DIRECTEUR GENERAL' }}</td>
    </tr>
    <tr>
      <td style="height:32px;">&nbsp;</td>
    </tr>
    <tr>
      <td class="sign-name">{{ ($signatoryName ?? '') !== '' ? $signatoryName : 'Prénoms et Noms' }}</td>
    </tr>
    <tr>
      <td class="sign-grade">{{ ($signatoryGrade ?? '') !== '' ? $signatoryGrade : 'Grade ou titre honorifique' }}</td>
    </tr>
  </table>

  <div class="bottom">{{ $contact }}</div>
</body>
</html>
