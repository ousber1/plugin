<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Ticket {{ $sale->number }}</title><link rel="stylesheet" href="/assets/app.css?v={{ filemtime(public_path('assets/app.css')) }}">
<style>body{background:#EEF0F5}@media print{body{background:#fff}.no-print{display:none}@page{size:80mm auto;margin:0}}</style></head>
<body>
<div class="no-print center" style="padding:12px"><button class="btn btn-sm" onclick="print()">Imprimer</button> <a class="btn btn-sm btn-ghost" href="{{ route('admin.pos') }}">Retour caisse</a></div>
<div class="ticket">
  <h2>{{ setting('brand.name') }}</h2>
  <div class="c">{{ setting('company.legal_name') }}<br>{{ setting('company.address') }}<br>@if(setting('company.phone'))Tél. {{ setting('company.phone') }}<br>@endif @if(setting('company.ice'))ICE {{ setting('company.ice') }}@endif</div>
  <hr>
  <div>Ticket : {{ $sale->number }}<br>Date : {{ $sale->created_at->format('d/m/Y H:i') }}<br>Caissier : {{ $sale->cashier?->name }}@if($sale->customer)<br>Client : {{ $sale->customer->displayName() }}@endif</div>
  <hr>
  <table>
    @foreach($sale->items as $i)
      <tr><td colspan="2">{{ $i->name }}</td></tr>
      <tr><td>{{ rtrim(rtrim((string) $i->quantity, '0'), '.') }} × {{ number_format((float) $i->unit_price_ttc, 2, ',', ' ') }}@if((float) $i->discount > 0) − {{ number_format((float) $i->discount, 2, ',', ' ') }}@endif</td><td class="r">{{ number_format((float) $i->total_ttc, 2, ',', ' ') }}</td></tr>
    @endforeach
  </table>
  <hr>
  <table>
    <tr><td>Total HT</td><td class="r">{{ number_format((float) $sale->subtotal_ht, 2, ',', ' ') }}</td></tr>
    <tr><td>TVA</td><td class="r">{{ number_format((float) $sale->tax_total, 2, ',', ' ') }}</td></tr>
    <tr><td><b>TOTAL TTC</b></td><td class="r"><b>{{ number_format((float) $sale->total_ttc, 2, ',', ' ') }} DH</b></td></tr>
    @foreach($sale->payments->where('type', 'payment') as $p)<tr><td>{{ __('payment.'.$p->method) }}</td><td class="r">{{ number_format((float) $p->amount, 2, ',', ' ') }}</td></tr>@endforeach
    @if((float) $sale->change_given > 0)<tr><td>Rendu</td><td class="r">{{ number_format((float) $sale->change_given, 2, ',', ' ') }}</td></tr>@endif
  </table>
  @if((float) $sale->refunded_total > 0)<hr><div>Remboursé : {{ number_format((float) $sale->refunded_total, 2, ',', ' ') }} DH</div>@endif
  <hr>
  <div class="c">{{ setting_t('retail.receipt_footer') }}</div>
</div>
<script>if (location.search.indexOf('auto') > -1) window.print()</script>
</body></html>
