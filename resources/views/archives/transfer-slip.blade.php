<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Bordereau de versement {{ $transfer->reference }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    h2 { font-size: 12px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
    .muted { color: #555; }
    .meta td { padding: 3px 8px 3px 0; vertical-align: top; }
    .meta td:first-child { width: 160px; color: #444; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th, table.items td { border: 1px solid #bbb; padding: 5px 6px; text-align: left; }
    table.items th { background: #f0f0f0; font-size: 10px; }
    .sign { margin-top: 36px; width: 100%; }
    .sign td { width: 50%; vertical-align: top; padding-top: 12px; }
    .box { border-top: 1px solid #333; margin-top: 48px; padding-top: 4px; width: 70%; }
  </style>
</head>
<body>
  <h1>Bordereau de versement aux archives</h1>
  <div class="muted">E-Trésor — Archives institutionnelles (SAE)</div>

  <h2>Identification</h2>
  <table class="meta">
    <tr><td>Référence</td><td><strong>{{ $transfer->reference }}</strong></td></tr>
    <tr><td>Intitulé</td><td>{{ $transfer->title ?: '—' }}</td></tr>
    <tr><td>Statut</td><td>{{ $transfer->status instanceof \BackedEnum ? $transfer->status->value : $transfer->status }}</td></tr>
    <tr><td>Structure</td><td>{{ $transfer->structure?->name ?: '—' }}</td></tr>
    <tr><td>Unité productrice</td><td>{{ $transfer->producerUnit?->name ?: '—' }}</td></tr>
    <tr><td>Support</td><td>{{ $transfer->medium ?: 'electronique' }}</td></tr>
    <tr><td>Typologie</td><td>{{ $transfer->typology ?: '—' }}</td></tr>
    <tr><td>Canal</td><td>{{ $transfer->source_ref ?: ($transfer->source_module ?: '—') }}</td></tr>
    <tr><td>Créé par</td><td>{{ $transfer->creator?->name ?: '—' }} le {{ optional($transfer->created_at)->format('d/m/Y H:i') }}</td></tr>
    @if($transfer->submitted_at)
      <tr><td>Soumis le</td><td>{{ optional($transfer->submitted_at)->format('d/m/Y H:i') }}</td></tr>
    @endif
  </table>

  @if($transfer->notes)
    <h2>Notes</h2>
    <p>{{ $transfer->notes }}</p>
  @endif

  <h2>Pièces versées ({{ $transfer->items->count() }})</h2>
  <table class="items">
    <thead>
      <tr>
        <th style="width:28px">N°</th>
        <th>Intitulé</th>
        <th>Référence doc.</th>
        <th>Checksum</th>
        <th>Classement prévu</th>
      </tr>
    </thead>
    <tbody>
      @foreach($transfer->items as $index => $item)
        @php
          $meta = is_array($item->metadata) ? $item->metadata : [];
          $classement = collect([
            isset($meta['fonds_id']) ? 'Fonds #'.$meta['fonds_id'] : null,
            isset($meta['series_id']) ? 'Série #'.$meta['series_id'] : null,
            isset($meta['classification_node_id']) ? 'Nœud #'.$meta['classification_node_id'] : null,
          ])->filter()->implode(' · ');
        @endphp
        <tr>
          <td>{{ $index + 1 }}</td>
          <td>
            {{ $item->title ?: '—' }}
            @if(!empty($meta['date_start']) || !empty($meta['date_end']))
              <div class="muted">{{ $meta['date_start'] ?? '?' }} → {{ $meta['date_end'] ?? '?' }}</div>
            @endif
          </td>
          <td>{{ $item->document?->reference ?: ($meta['reference'] ?? '—') }}</td>
          <td style="font-size:9px">{{ $item->checksum ? substr($item->checksum, 0, 16).'…' : ($meta['qc_checksum'] ?? '—') }}</td>
          <td>{{ $classement !== '' ? $classement : '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="sign">
    <tr>
      <td>
        <strong>Le producteur / verseur</strong>
        <div class="box">Nom, date, signature</div>
      </td>
      <td>
        <strong>Le service des archives</strong>
        <div class="box">Réception / contrôle</div>
      </td>
    </tr>
  </table>
</body>
</html>
