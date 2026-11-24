@extends('layouts.admin')
@section('title', $supplier->name)
@section('content')
<div class="page-head"><div><h1>@icon('store') {{ $supplier->name }}</h1><div class="sub">{{ $supplier->contact_name }} · {{ $supplier->phone }} · {{ $supplier->email }} · {{ $supplier->city }} {{ $supplier->ice ? '· ICE '.$supplier->ice : '' }}</div></div>
  <div class="flex"><a class="btn btn-ghost" href="{{ route('admin.r.edit', ['suppliers', $supplier->id]) }}">@icon('edit') Modifier</a><a class="btn btn-accent" href="{{ route('admin.po.create', ['supplier' => $supplier->id]) }}">@icon('box') Nouveau bon de commande</a></div></div>
<div class="kpis">
  <div class="kpi k-ink"><div class="k-ic">@icon('box')</div><div><div class="v">{{ money($balance['received']) }}</div><div class="l">Marchandises reçues HT</div></div></div>
  <div class="kpi k-ok"><div class="k-ic">@icon('wallet')</div><div><div class="v">{{ money($balance['paid']) }}</div><div class="l">Déjà réglé</div></div></div>
  @if((float) $balance['due'] >= 0)
    <div class="kpi {{ (float) $balance['due'] > 0 ? 'alert-kpi' : 'k-ok' }}"><div class="k-ic">@icon('alert')</div><div><div class="v">{{ money($balance['due']) }}</div><div class="l">Reste à payer</div></div></div>
  @else
    <div class="kpi k-ok"><div class="k-ic">@icon('check')</div><div><div class="v">{{ money(abs((float) $balance['due'])) }}</div><div class="l">Avance versée (à déduire des prochaines livraisons)</div></div></div>
  @endif
</div>
<div class="split">
  <div>
    <div class="card table-wrap" style="padding:0"><div style="padding:16px 18px 0"><h3>Bons de commande</h3></div><table><thead><tr><th>N°</th><th>Date</th><th>Réf. fournisseur</th><th>Statut</th><th class="num">Total HT</th><th class="num">Reçu HT</th></tr></thead><tbody>
      @forelse($supplier->purchaseOrders as $po)<tr><td><a href="{{ route('admin.po.show', $po) }}"><b>{{ $po->number }}</b></a></td><td>{{ d($po->created_at) }}</td><td>{{ $po->supplier_reference }}</td><td><span class="pill">{{ __('po_status.'.$po->status) }}</span></td><td class="num">{{ money($po->total) }}</td><td class="num">{{ money($po->receivedValue()) }}</td></tr>@empty<tr><td colspan="6"><div class="empty">Aucun bon de commande.</div></td></tr>@endforelse
    </tbody></table></div>
    <div class="card"><div class="card-head"><h3>Articles du magasin fournis</h3></div>
      @forelse($supplier->retailProducts as $p)<div class="flex between small" style="padding:5px 0;border-bottom:1px solid var(--line-2)"><a href="{{ route('admin.retail.products.show', $p) }}">{{ $p->name }}</a><span>stock {{ (float) $p->stock_qty }} · coût {{ money(round((float) $p->cost_price, 2)) }}</span></div>@empty<p class="muted small">Aucun article rattaché.</p>@endforelse
      @if($supplier->materials->isNotEmpty())<h4 style="margin-top:14px">Matières atelier</h4>@foreach($supplier->materials as $m)<div class="small">{{ $m->name }} – {{ (float) $m->stock_qty }} {{ $m->unit }}</div>@endforeach @endif
    </div>
  </div>
  <div>
    @can('purchasing.manage')
    <form class="card" method="post" action="{{ route('admin.suppliers.payment', $supplier) }}" data-once>@csrf
      <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
      <div class="card-head"><h3>@icon('wallet') Enregistrer un règlement</h3></div>
      <div class="grid-2"><div class="field"><label class="small">Montant (DH)</label><input type="number" step="0.01" min="0.01" name="amount" required value="{{ (float) $balance['due'] > 0 ? $balance['due'] : '' }}"></div>
        <div class="field"><label class="small">Date</label><input type="date" name="paid_at" value="{{ today()->toDateString() }}" required></div>
        <div class="field"><label class="small">Moyen</label><select name="method">@foreach(['bank_transfer', 'cheque', 'cash', 'card_in_store'] as $m)<option value="{{ $m }}">{{ __('payment.'.$m) }}</option>@endforeach</select></div>
        <div class="field"><label class="small">Référence</label><input name="reference" placeholder="N° chèque / virement"></div></div>
      <div class="field"><label class="small">Bon de commande (facultatif)</label><select name="purchase_order_id"><option value="">—</option>@foreach($supplier->purchaseOrders as $po)<option value="{{ $po->id }}">{{ $po->number }} – {{ money($po->total) }}</option>@endforeach</select></div>
      <button class="btn btn-block">Enregistrer le règlement</button>
    </form>
    @endcan
    <div class="card"><div class="card-head"><h3>Historique des règlements</h3></div>
      @forelse($supplier->payments->sortByDesc('paid_at') as $p)<div class="flex between small" style="padding:6px 0;border-bottom:1px solid var(--line-2)"><span>{{ d($p->paid_at) }} · {{ __('payment.'.$p->method) }} {{ $p->reference }} @if($p->user)<span class="muted">({{ $p->user->name }})</span>@endif</span><b>{{ money($p->amount) }}</b></div>@empty<p class="muted small">Aucun règlement.</p>@endforelse
      @if($supplier->notes)<p class="small" style="margin-top:10px">{{ $supplier->notes }}</p>@endif
    </div>
  </div>
</div>
@endsection
