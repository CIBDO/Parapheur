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
      <div class="ministry">MINISTÈRE DE L'ÉCONOMIE ET DES FINANCES.</div>
      <div class="direction">{{ $structureEntete ?? 'DIRECTION GÉNÉRALE DU TRÉSOR ET DE LA COMPTABILITÉ PUBLIQUE' }}</div>
    </td>
    <td class="republic" width="190" align="center" valign="middle">
      <div class="republic-title">République du Mali</div>
      <div style="margin:3px 0 2px;">
        <img src="{{ str_replace('\\', '/', public_path('images/drapeau-mali.png')) }}" width="78" height="16" alt="">
      </div>
      <div class="motto">Un Peuple – Un But – Une Foi</div>
    </td>
  </tr>
</table>
