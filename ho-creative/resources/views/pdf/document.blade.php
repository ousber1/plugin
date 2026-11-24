@php
  $c = $s['company'] ?? []; $cu = $s['customer'] ?? []; $t = $s['totals'] ?? [];
  $prices = empty($s['no_prices']);
  $m = fn ($v) => \App\Support\Money::format($v, $rtl ? 'ar' : 'fr');
  $color = $c['color'] ?? '#1B1F3B'; $accent = $c['accent'] ?? '#E4007C';
  $align = $rtl ? 'right' : 'left'; $opp = $rtl ? 'left' : 'right';
@endphp
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}"><head><meta charset="utf-8">
<style>
  body { font-size: 9.5pt; color: #1B1F3B; }
  .head td { vertical-align: top; }
  .brand { font-size: 18pt; font-weight: bold; color: {{ $color }}; }
  .title { font-size: 15pt; font-weight: bold; color: {{ $accent }}; text-align: {{ $opp }}; }
  .box { border: 0.3mm solid #ddd; padding: 3mm; }
  table.lines { width: 100%; border-collapse: collapse; margin-top: 6mm; }
  table.lines th { background: {{ $color }}; color: #fff; padding: 2mm; font-size: 8.5pt; text-align: {{ $align }}; }
  table.lines td { border-bottom: 0.2mm solid #e5e5e5; padding: 2mm; vertical-align: top; }
  .num { text-align: {{ $opp }}; white-space: nowrap; }
  .muted { color: #666; font-size: 8pt; }
  table.totals { width: 75mm; margin-{{ $opp }}: 0; margin-top: 4mm; border-collapse: collapse; }
  table.totals td { padding: 1.5mm 2mm; }
  .grand td { background: {{ $color }}; color: #fff; font-weight: bold; font-size: 11pt; }
  .footer { font-size: 7.5pt; color: #666; text-align: center; border-top: 0.2mm solid #ddd; padding-top: 2mm; }
</style></head>
<body>
<htmlpagefooter name="f"><div class="footer">
  {{ $c['name'] ?? '' }} · {{ $c['address'] ?? '' }} {{ $c['city'] ?? '' }} · {{ $c['phone'] ?? '' }} {{ $c['email'] ?? '' }}<br>
  @if(! empty($c['ice']))ICE : {{ $c['ice'] }} · @endif @if(! empty($c['rc']))RC : {{ $c['rc'] }} · @endif @if(! empty($c['if']))IF : {{ $c['if'] }} · @endif @if(! empty($c['patente']))Patente : {{ $c['patente'] }} · @endif @if(! empty($c['cnss']))CNSS : {{ $c['cnss'] }}@endif
  @if(! empty($c['rib']))<br>{{ $c['bank'] ?? '' }} – RIB : {{ $c['rib'] }}@endif
  @if(! empty($c['footer'][$rtl ? 'ar' : 'fr']))<br>{{ $c['footer'][$rtl ? 'ar' : 'fr'] }}@endif
  <br>{PAGENO}/{nbpg}
</div></htmlpagefooter>
<sethtmlpagefooter name="f" value="on" />

<table class="head" width="100%"><tr>
  <td width="55%">
    @if(! empty($c['logo']) && is_file(public_path(ltrim($c['logo'], '/'))))<img src="{{ public_path(ltrim($c['logo'], '/')) }}" style="height:16mm"><br>@else<div class="brand">{{ $c['brand'] ?? 'HO CREATIVE' }}</div>@endif
    <div class="muted">{{ $c['name'] ?? '' }}<br>{{ $c['address'] ?? '' }} {{ $c['city'] ?? '' }}<br>{{ $c['phone'] ?? '' }} {{ $c['email'] ?? '' }}</div>
  </td>
  <td width="45%">
    <div class="title">{{ __('doc.'.$doc->type) }}</div>
    <div style="text-align:{{ $opp }}"><strong>{{ __('N°') }} {{ $doc->number }}</strong><br>{{ __('Date') }} : {{ \Illuminate\Support\Carbon::parse($s['issued_at'] ?? $doc->issued_at)->format('d/m/Y') }}<br>
      @if(! empty($s['reference'])){{ __('Référence') }} : {{ $s['reference'] }}<br>@endif
      @if(! empty($s['valid_until'])){{ __('Valable jusqu\'au') }} : {{ \Illuminate\Support\Carbon::parse($s['valid_until'])->format('d/m/Y') }}@endif</div>
  </td>
</tr></table>

<table width="100%" style="margin-top:6mm"><tr><td width="50%"></td><td width="50%" class="box">
  <strong>{{ $cu['company'] ?: $cu['name'] ?? '' }}</strong><br>
  @if(! empty($cu['company'])){{ $cu['name'] }}<br>@endif
  @if(! empty($cu['address'])){{ $cu['address']['line1'] ?? '' }} {{ $cu['address']['line2'] ?? '' }}<br>{{ $cu['address']['postal_code'] ?? '' }} {{ $cu['address']['city'] ?? '' }}<br>@endif
  {{ $cu['phone'] ?? '' }} {{ $cu['email'] ?? '' }}
  @if(! empty($cu['ice']))<br>ICE : {{ $cu['ice'] }}@endif
</td></tr></table>

<table class="lines">
  <thead><tr><th>{{ __('Désignation') }}</th><th class="num">{{ __('Qté') }}</th>@if($prices)<th class="num">{{ __('P.U. HT') }}</th><th class="num">{{ __('Total HT') }}</th>@endif</tr></thead>
  <tbody>
  @foreach($s['lines'] ?? [] as $l)
    <tr><td><strong>{{ $l['description'] }}</strong>@if(! empty($l['details']))<br><span class="muted">{{ $l['details'] }}</span>@endif @if(! empty($l['discount_note']))<br><span class="muted">{{ $l['discount_note'] }}</span>@endif</td>
      <td class="num">{{ $l['qty'] }}</td>@if($prices)<td class="num">{{ $m(round((float) $l['unit_price'], 2)) }}</td><td class="num">{{ $m($l['total']) }}</td>@endif</tr>
  @endforeach
  </tbody>
</table>

@if($prices && $t)
<table class="totals" align="{{ $opp }}">
  @if(empty($s['no_tax']))
    <tr><td>{{ __('Sous-total HT') }}</td><td class="num">{{ $m($t['subtotal'] ?? 0) }}</td></tr>
    @if((float) ($t['discount'] ?? 0) > 0)<tr><td>{{ __('Remise') }}</td><td class="num">−{{ $m($t['discount']) }}</td></tr>@endif
    @if((float) ($t['shipping'] ?? 0) > 0)<tr><td>{{ __('Livraison') }}</td><td class="num">{{ $m($t['shipping']) }}</td></tr>@endif
    <tr><td>{{ __('Total HT') }}</td><td class="num">{{ $m($t['ht'] ?? $t['subtotal'] ?? 0) }}</td></tr>
    <tr><td>{{ __('TVA') }} @if(($t['tax_rate'] ?? '') !== ''){{ rtrim(rtrim((string) $t['tax_rate'], '0'), '.') }} %@endif</td><td class="num">{{ $m($t['tax'] ?? 0) }}</td></tr>
  @endif
  <tr class="grand"><td>{{ empty($s['no_tax']) ? __('Total TTC') : __('Montant') }}</td><td class="num">{{ $m($t['total'] ?? 0) }}</td></tr>
  @isset($t['paid'])<tr><td>{{ __('Déjà payé') }}</td><td class="num">{{ $m($t['paid']) }}</td></tr><tr><td><strong>{{ __('Reste à payer') }}</strong></td><td class="num"><strong>{{ $m($t['balance']) }}</strong></td></tr>@endisset
  @if(isset($t['order_total']))<tr><td>{{ __('Total commande') }}</td><td class="num">{{ $m($t['order_total']) }}</td></tr><tr><td>{{ __('Reste à payer') }}</td><td class="num">{{ $m($t['balance']) }}</td></tr>@endif
</table>
@endif

@if(! empty($s['payment_terms']))<p style="margin-top:8mm">{{ $s['payment_terms'] }}</p>@endif
@if(! empty($s['notes']))<p class="muted" style="white-space:pre-wrap">{{ $s['notes'] }}</p>@endif
@if($doc->type === 'quote')<p style="margin-top:10mm">{{ __('Bon pour accord (date, nom, signature) :') }}</p>@endif
@if($doc->type === 'delivery_note')<p style="margin-top:10mm">{{ __('Reçu conforme – nom, date et signature du client :') }}</p>@endif
@if($doc->type === 'proforma')<p class="muted">{{ __('Document sans valeur comptable.') }}</p>@endif
</body></html>
