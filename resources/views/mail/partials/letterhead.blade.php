@if(!empty($saisiPar))
  <div class="saisi">Saisi par {{ $saisiPar }}</div>
@endif
<table class="headbox" width="100%" cellspacing="0" cellpadding="6">
  <tr>
    <td class="emblem" width="72" align="center" valign="middle">
      @if(!empty($emblem))
        <img src="{{ $emblem }}" width="56" height="56" alt="">
      @endif
    </td>
    <td class="identity" align="center" valign="middle">
      @php
        $foldHeading = function (string $value): string {
          $value = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), 'UTF-8');
          $value = strtr($value, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Ÿ' => 'Y',
            'Œ' => 'OE', 'Æ' => 'AE',
          ]);

          return preg_replace('/[^A-Z0-9 ]/u', '', $value) ?? $value;
        };
        $headerText = trim((string) ($structureEntete ?? ''));
        $lineText = trim((string) ($structureLine ?? ''));
        $sameStructure = $headerText !== '' && $foldHeading($headerText) === $foldHeading($lineText);
      @endphp
      <div class="ministry">{{ $ministry ?? "MINISTÈRE DE L'ÉCONOMIE ET DES FINANCES." }}</div>
      <div class="direction">{{ $structureEntete ?? 'DIRECTION GÉNÉRALE DU TRÉSOR ET DE LA COMPTABILITÉ PUBLIQUE' }}</div>
      @if($lineText !== '' && ! $sameStructure)
        <div class="direction">{{ $structureLine }}</div>
      @endif
      @if(!empty($headerExtra))
        <div style="margin-top:3px;font-size:9px;">{{ $headerExtra }}</div>
      @endif
    </td>
    <td class="republic" width="190" align="center" valign="middle">
      <div class="republic-title">{{ $republic ?? 'République du Mali' }}</div>
      <div style="margin:3px 0 2px;">
        <img src="{{ str_replace('\\', '/', public_path('images/drapeau-mali.png')) }}" width="78" height="16" alt="">
      </div>
      <div class="motto">{{ $motto ?? 'Un Peuple – Un But – Une Foi' }}</div>
    </td>
  </tr>
</table>
@if(!empty($footer))
  <div style="text-align:center;font-size:9px;margin:-4px 0 8px;">{{ $footer }}</div>
@endif
