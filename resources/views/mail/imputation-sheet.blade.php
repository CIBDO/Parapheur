<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 12mm 12mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12px; }
    .saisi { text-align: right; font-style: italic; font-size: 11px; margin: 0 0 2px; }
    table.headbox { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 16px; }
    table.headbox td { border: 1px solid #000; }
    table.headbox table td { border: none; padding: 0; }
    .ministry { font-size: 12px; font-weight: bold; font-style: italic; }
    .direction { margin-top: 4px; font-size: 10px; }
    .republic-title { font-weight: bold; font-size: 13px; text-align: center; }
    table.flag { margin: 0; border-collapse: collapse; }
    .g { background: #14b53a; } .y { background: #fcd116; } .r { background: #ce1126; }
    .motto { font-size: 9px; white-space: nowrap; text-align: center; }
    h1 { text-align: center; font-size: 15px; letter-spacing: 0.6px; margin: 6px 0 14px; }
    table.meta, table.grid { width: 100%; border-collapse: collapse; }
    table.meta td, table.grid td, table.grid th { border: 1px solid #000; padding: 5px 6px; }
    td.h, th.h { background: #d9d9d9; font-weight: bold; text-align: center; font-size: 11px; }
    td.v { height: 20px; }
    table.grid { margin-top: 16px; }
    table.grid th { background: #d9d9d9; font-size: 10px; }
    table.grid td { height: 28px; vertical-align: middle; text-align: center; }
    table.grid td.note { text-align: left; }
  </style>
</head>
<body>
  @include('mail.partials.letterhead', [
    'saisiPar' => $saisiPar ?? '',
    'structureEntete' => $structureEntete ?? '',
    'structureLine' => $structureLine ?? null,
    'ministry' => $ministry ?? null,
    'headerExtra' => $headerExtra ?? null,
    'footer' => $footer ?? null,
    'republic' => $republic ?? null,
    'motto' => $motto ?? null,
    'emblem' => $emblem ?? null,
  ])

  <h1>TOUTES LES IMPUTATIONS EFFECTUÉES</h1>

  <table class="meta">
    <tr>
      <td class="h" width="50%">N° COURRIER</td>
      <td class="h">N° MINISTERE</td>
    </tr>
    <tr>
      <td class="v">{{ $numeroCourrier }}</td>
      <td class="v">{{ $numeroMinistere }}</td>
    </tr>
    <tr>
      <td class="h">ORIGINE</td>
      <td class="h">REF COURRIER</td>
    </tr>
    <tr>
      <td class="v">{{ $origine }}</td>
      <td class="v">{{ $reference }}</td>
    </tr>
    <tr>
      <td class="h">DATE COURRIER</td>
      <td class="h">DATE DE RECEPTION</td>
    </tr>
    <tr>
      <td class="v">{{ $dateCourrier }}</td>
      <td class="v">{{ $dateReception }}</td>
    </tr>
    <tr>
      <td class="h" colspan="2">OBJET</td>
    </tr>
    <tr>
      <td class="v" colspan="2">{{ $objet }}</td>
    </tr>
  </table>

  <table class="grid">
    <thead>
      <tr>
        <th class="h" width="16%">DATE<br>D'IMPUTATION</th>
        <th class="h" width="16%">PRENOM</th>
        <th class="h" width="16%">NOM</th>
        <th class="h">ANNOTATION(S)</th>
        <th class="h" width="16%">SIGNATURE</th>
      </tr>
    </thead>
    <tbody>
      @foreach($imputations as $row)
        <tr>
          <td>{{ $row['date'] }}</td>
          <td>{{ $row['prenom'] }}</td>
          <td>{{ $row['nom'] }}</td>
          <td class="note">{{ $row['annotation'] }}</td>
          <td></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
