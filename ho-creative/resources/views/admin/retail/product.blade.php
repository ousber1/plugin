@extends('layouts.admin')
@section('title', $product->name)
@section('content')
<div class="page-head"><div><h1>@icon('tag') {{ $product->name }} @if(! $product->is_active)<span class="pill">Inactif</span>@endif</h1><div class="sub">{{ $product->sku }} {{ $product->barcode ? '· EAN '.$product->barcode : '' }} · {{ $product->category?->name }}</div></div>
  @can('retail.manage')<a class="btn btn-ghost" href="{{ route('admin.retail.products.edit', $product) }}">@icon('edit') Modifier</a>@endcan</div>
<div class="kpis">
  <div class="kpi {{ $product->isLow() ? 'alert-kpi' : 'k-ok' }}"><div class="k-ic">@icon('box')</div><div><div class="v">{{ $product->track_stock ? (float) $product->stock_qty : '∞' }}</div><div class="l">En stock (seuil {{ (float) $product->low_stock_threshold }})</div></div></div>
  <div class="kpi k-accent"><div class="k-ic">@icon('tag')</div><div><div class="v">{{ money($product->price_ttc) }}</div><div class="l">Prix TTC · {{ money($product->priceHt()) }} HT · TVA {{ (float) $product->tax_rate }} %</div></div></div>
  @can('finance.view')<div class="kpi k-ink"><div class="k-ic">@icon('chart')</div><div><div class="v">{{ $product->marginPct() !== null ? $product->marginPct().' %' : '—' }}</div><div class="l">Marge (coût moyen {{ money(round((float) $product->cost_price, 2)) }})</div></div></div>@endcan
  <div class="kpi"><div class="k-ic">@icon('receipt')</div><div><div class="v">{{ $sold30 }}</div><div class="l">Vendus sur 30 jours</div></div></div>
</div>
<div class="split">
  <div class="card table-wrap" style="padding:0"><div style="padding:16px 18px 0"><h3>Mouvements de stock</h3></div>
    <table><thead><tr><th>Date</th><th>Type</th><th class="num">Quantité</th><th class="num">Coût u.</th><th>Origine</th><th>Par</th></tr></thead><tbody>
    @forelse($movements as $m)<tr><td>{{ $m->created_at->format('d/m/Y H:i') }}</td><td><span class="pill {{ (float) $m->quantity >= 0 ? 'pill-ok' : 'pill-info' }}">{{ ['initial' => 'Stock initial', 'purchase' => 'Achat', 'sale' => 'Vente', 'return' => 'Retour client', 'adjustment' => 'Inventaire', 'loss' => 'Perte / casse'][$m->type] ?? $m->type }}</span></td>
      <td class="num"><b>{{ (float) $m->quantity > 0 ? '+' : '' }}{{ (float) $m->quantity }}</b></td><td class="num">{{ round((float) $m->unit_cost, 2) }}</td><td class="small">@if($m->sale)<a href="{{ route('admin.retail.sales.show', $m->sale) }}">{{ $m->sale->number }}</a>@else{{ $m->notes }}@endif</td><td class="small">{{ $m->user?->name }}</td></tr>
    @empty<tr><td colspan="6"><div class="empty">Aucun mouvement.</div></td></tr>@endforelse
    </tbody></table><div style="padding:0 18px 12px">{{ $movements->links('partials.pagination') }}</div></div>
  @can('retail.manage')
  <form class="card" method="post" action="{{ route('admin.retail.products.adjust', $product) }}" data-once>@csrf
    <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
    <div class="card-head"><h3>Mouvement manuel</h3></div>
    <div class="field"><label class="small">Type</label><select name="mode"><option value="receipt">Entrée (achat hors bon de commande)</option><option value="count">Inventaire : fixer le stock à…</option><option value="loss">Perte / casse / vol</option></select></div>
    <div class="grid-2"><div class="field"><label class="small">Quantité</label><input type="number" step="0.001" min="0" name="quantity" required></div><div class="field"><label class="small">Coût u. HT (entrée)</label><input type="number" step="0.0001" min="0" name="unit_cost" value="{{ round((float) $product->cost_price, 4) }}"></div></div>
    <div class="field"><label class="small">Motif *</label><input name="reason" required></div>
    <button class="btn btn-block">Enregistrer</button>
    <p class="help">Inventaires et pertes sont tracés dans le journal d'audit.</p>
  </form>
  @endcan
</div>
@endsection
