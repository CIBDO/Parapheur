<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Compte rendu</title>
  <style>
    @php
      $fontDir = str_replace('\\', '/', resource_path('fonts/mail'));
    @endphp
    @font-face { font-family: 'Times New Roman'; font-style: normal; font-weight: normal; src: url('{{ $fontDir }}/times.ttf') format('truetype'); }
    @font-face { font-family: 'Times New Roman'; font-style: normal; font-weight: bold; src: url('{{ $fontDir }}/timesbd.ttf') format('truetype'); }
    @page { margin: 1.4cm 1.6cm 1.6cm; }
    body {
      font-family: 'Times New Roman', Times, serif;
      color: #111;
      font-size: 11.5pt;
      line-height: 1.35;
    }
    table.banner, table.minutes-letterhead { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    table.banner td, table.minutes-letterhead td { border: none; vertical-align: top; }
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
    .rule-right { width: 58%; margin-left: auto; margin-right: 0; }
    .republic { font-weight: bold; font-size: 12pt; text-align: right; text-transform: uppercase; }
    .motto { text-align: right; font-size: 10.5pt; margin-top: 3px; }
    h1, .doc-title { text-align: center; font-size: 15pt; text-transform: uppercase; margin: 8px 0 4px; }
    h2 { font-size: 12.5pt; margin: 14px 0 6px; text-transform: uppercase; }
    h3 { font-size: 11.5pt; margin: 10px 0 4px; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.data th, table.data td { border: 1px solid #333; padding: 6px 7px; font-size: 10.5pt; }
    table.data th { background: #f2f2f2; text-align: center; }
  </style>
</head>
<body>
  {!! $body !!}
</body>
</html>
