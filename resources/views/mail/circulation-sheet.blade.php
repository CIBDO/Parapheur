<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 8mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11px; }
    .saisi { text-align: right; font-style: italic; font-size: 11px; margin: 0 0 2px; }
    table.headbox { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 8px; }
    table.headbox td { border: 1px solid #000; }
    table.headbox table td { border: none; padding: 0; }
    .ministry { font-size: 12px; font-weight: bold; font-style: italic; }
    .direction { margin-top: 4px; font-size: 10px; }
    .republic-title { font-weight: bold; font-size: 13px; text-align: center; }
    table.flag { margin: 0; border-collapse: collapse; }
    .g { background: #14b53a; } .y { background: #fcd116; } .r { background: #ce1126; }
    .motto { font-size: 9px; white-space: nowrap; text-align: center; }
    h1 { text-align: center; font-size: 14px; margin: 6px 0 8px; letter-spacing: 0.3px; }
    table.meta, table.grid, table.signs { width: 100%; border-collapse: collapse; }
    table.meta td, table.grid td { border: 1px solid #000; padding: 5px 6px; vertical-align: middle; }
    table.meta td.h { font-weight: bold; text-align: center; }
    table.meta td.v { height: 16px; }
    .section { text-align: center; font-style: italic; font-weight: bold; margin: 7px 0 3px; font-size: 13px; }
    table.grid td { font-size: 10px; height: 16px; }
    table.ann td { font-size: 9px; padding: 2px 3px; height: 14px; }
    table.services td { font-size: 9px; line-height: 1.15; text-align: center; padding: 3px 2px; height: auto; }
    .box { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; text-align: center; font-size: 9px; line-height: 11px; font-weight: bold; }
    table.signs td { border: none; text-align: center; font-weight: bold; text-decoration: underline; padding-top: 12px; padding-bottom: 36px; font-size: 12px; }
    .suite { margin-top: 8px; background: #bdbdbd; color: #fff; text-align: center; font-style: italic; font-weight: bold; padding: 4px 0; font-size: 13px; }
  </style>
</head>
<body>
  @include('mail.partials.letterhead', [
    'saisiPar' => $saisi_par ?? '',
    'structureEntete' => $structure_entete ?? '',
    'structureLine' => $structure_line ?? null,
    'ministry' => $ministry ?? null,
    'headerExtra' => $header_extra ?? null,
    'footer' => $footer ?? null,
    'republic' => $republic ?? null,
    'motto' => $motto ?? null,
    'emblem' => $emblem ?? null,
  ])

  <h1>FICHE DE CIRCULATION COURRIER &lt;&lt;{{ $sens ?? 'ARRIVEE' }}&gt;&gt;</h1>

  <table class="meta">
    <tr>
      <td class="h" width="34%">N°Enregistrement</td>
      <td class="h" width="33%">Date d'arrivée</td>
      <td class="h">Origine</td>
    </tr>
    <tr>
      <td class="v">{{ $numero_enregistrement }}</td>
      <td class="v">{{ $date_reception }}</td>
      <td class="v">{{ $origine }}</td>
    </tr>
  </table>

  <table class="meta" style="margin-top: 8px;">
    <tr>
      <td class="h" width="28%">Référence</td>
      <td class="h" width="24%">Numéro Ministère</td>
      <td class="h" width="24%">Date du courrier</td>
      <td class="h">Type du courrier</td>
    </tr>
    <tr>
      <td class="v">{{ $reference_externe }}</td>
      <td class="v">{{ $numero_ministere ?? '' }}</td>
      <td class="v">{{ $date_correspondance }}</td>
      <td class="v">{{ $type_courrier }}</td>
    </tr>
  </table>

  <div class="section">Objet</div>
  <table class="meta">
    <tr>
      <td class="v">{{ $objet }}</td>
    </tr>
  </table>

  @php
    $services = array_values(array_filter($services_imputation ?? [], fn ($service) => ($service['label'] ?? '') !== ''));
    $perRow = count($services) > 16 ? 6 : 4;
    $serviceRows = $services === [] ? [[]] : array_chunk($services, $perRow);
  @endphp
  <div class="section">Imputation</div>
  <table class="grid services">
    @foreach($serviceRows as $row)
      <tr>
        <td width="4%"></td>
        @foreach($row as $service)
          <td>{{ $service['label'] }}</td>
        @endforeach
        @for($i = count($row); $i < $perRow; $i++)
          <td></td>
        @endfor
      </tr>
    @endforeach
  </table>

  <div class="section">Annotation</div>
  <table class="grid ann">
    @foreach(($annotations_grille ?? []) as $row)
      <tr>
        @foreach($row as $cell)
          <td width="25%">
            <table cellspacing="0" cellpadding="0"><tr>
              <td style="width:12px;height:12px;border:1px solid #000;text-align:center;font-size:9px;font-weight:bold;">{{ !empty($cell['checked']) ? 'X' : '' }}</td>
              <td style="padding-left:4px;border:none;">{{ $cell['label'] }}</td>
            </tr></table>
          </td>
        @endforeach
      </tr>
    @endforeach
  </table>

  <table class="signs">
    <tr>
      <td width="50%"><u>{{ $signataire_gauche ?? 'Directeur général Adjoint' }}</u></td>
      <td width="50%"><u>{{ $signataire_droite ?? 'Directeur Général' }}</u></td>
    </tr>
  </table>

  <div class="suite">Suite reservée</div>
</body>
</html>
