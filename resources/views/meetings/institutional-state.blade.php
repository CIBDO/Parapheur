<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>{{ $title ?? 'État institutionnel' }}</title>
  <style>
    @php
      $fontDir = str_replace('\\', '/', resource_path('fonts/mail'));
    @endphp
    @font-face { font-family: 'Times New Roman'; font-style: normal; font-weight: normal; src: url('{{ $fontDir }}/times.ttf') format('truetype'); }
    @font-face { font-family: 'Times New Roman'; font-style: normal; font-weight: bold; src: url('{{ $fontDir }}/timesbd.ttf') format('truetype'); }
    @font-face { font-family: 'Times New Roman'; font-style: italic; font-weight: normal; src: url('{{ $fontDir }}/timesi.ttf') format('truetype'); }
    @font-face { font-family: 'Times New Roman'; font-style: italic; font-weight: bold; src: url('{{ $fontDir }}/timesbi.ttf') format('truetype'); }
    @page { margin: 1.4cm 1.6cm 1.6cm; }
    body {
      font-family: 'Times New Roman', Times, serif;
      color: #111;
      font-size: 11.5pt;
      line-height: 1.35;
    }
    table.banner { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    table.banner td { border: none; vertical-align: top; }
    .ministry, .direction {
      display: inline-block;
      font-weight: bold;
      font-size: 11pt;
      text-align: center;
      text-transform: uppercase;
      line-height: 1.2;
      margin-bottom: 6px;
    }
    .rule {
      width: 72%;
      border-bottom: 1px solid #000;
      margin: 4px auto 0;
      height: 0;
    }
    .rule-right {
      width: 58%;
      margin-left: auto;
      margin-right: 0;
    }
    .republic {
      font-weight: bold;
      font-size: 12pt;
      text-align: right;
      text-transform: uppercase;
      line-height: 1.2;
    }
    .motto {
      text-align: right;
      font-size: 10.5pt;
      margin-top: 3px;
      line-height: 1.2;
    }
    .doc-title {
      text-align: center;
      font-weight: bold;
      font-size: 15pt;
      text-transform: uppercase;
      margin: 8px 0 4px;
      letter-spacing: 0.4px;
    }
    .doc-subtitle {
      text-align: center;
      font-size: 12pt;
      margin: 0 0 14px;
    }
    .meta {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
    }
    .meta td {
      border: none;
      padding: 2px 0;
      vertical-align: top;
    }
    .meta td.label {
      width: 28%;
      font-weight: bold;
    }
    table.data {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
      margin-bottom: 12px;
    }
    table.data th, table.data td {
      border: 1px solid #333;
      padding: 6px 7px;
      text-align: left;
      vertical-align: top;
      font-size: 10.5pt;
    }
    table.data th {
      background: #f2f2f2;
      font-weight: bold;
      text-align: center;
    }
    h2 {
      font-size: 12.5pt;
      margin: 14px 0 6px;
      text-transform: uppercase;
    }
    h3 {
      font-size: 11.5pt;
      margin: 10px 0 4px;
    }
    .sign-block {
      margin-top: 36px;
      text-align: right;
      padding-right: 24px;
    }
    .footer-ref {
      margin-top: 24px;
      font-size: 9pt;
      color: #444;
      border-top: 1px solid #999;
      padding-top: 6px;
    }
  </style>
</head>
<body>
  @include('meetings.partials.institutional-letterhead')

  <div class="doc-title">{{ $title }}</div>
  @if(!empty($subtitle))
    <div class="doc-subtitle">{{ $subtitle }}</div>
  @endif

  @if(!empty($metaRows))
    <table class="meta">
      @foreach($metaRows as $row)
        <tr>
          <td class="label">{{ $row['label'] }}</td>
          <td>{{ $row['value'] }}</td>
        </tr>
      @endforeach
    </table>
  @endif

  {!! $body !!}

  @if(!empty($signatory))
    <div class="sign-block">
      {{ $signatoryLabel ?? "L'autorité de convocation" }}<br>
      <strong>{{ $signatory }}</strong>
    </div>
  @endif

  {{-- <div class="footer-ref">
    {{ $reference ?? '' }}
    @if(!empty($date))
      — {{ $date }}
    @endif
  </div> --}}
</body>
</html>
