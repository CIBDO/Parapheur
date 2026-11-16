{{-- Entête institutionnelle — valeurs depuis config/meetings.php (modifiables dans le document généré) --}}
@php
  $lh = config('meetings.letterhead', []);
  $ministry1 = $lh['ministry_line_1'] ?? "MINISTÈRE DE L'ÉCONOMIE";
  $ministry2 = $lh['ministry_line_2'] ?? 'ET DES FINANCES';
  $direction1 = $lh['direction_line_1'] ?? 'DIRECTION GÉNÉRALE DU TRÉSOR';
  $direction2 = $lh['direction_line_2'] ?? 'ET DE LA COMPTABILITÉ PUBLIQUE';
  $republic = $lh['republic'] ?? 'RÉPUBLIQUE DU MALI';
  $motto = $lh['motto'] ?? 'Un Peuple - Un But - Une Foi';
@endphp
<table class="banner minutes-letterhead" width="100%" cellspacing="0" cellpadding="0">
  <tr>
    <td width="52%" class="banner-left" valign="top">
      <div class="ministry">
        {{ $ministry1 }}<br>
        {{ $ministry2 }}
        <div class="rule"></div>
      </div>
      <div class="direction">
        {{ $direction1 }}<br>
        {{ $direction2 }}
        <div class="rule"></div>
      </div>
    </td>
    <td width="48%" class="banner-right" valign="top" align="right">
      <div class="republic">{{ $republic }}</div>
      <div class="motto">{{ $motto }}</div>
      <div class="rule rule-right"></div>
    </td>
  </tr>
</table>
