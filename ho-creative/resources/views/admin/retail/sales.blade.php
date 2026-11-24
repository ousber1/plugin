@extends('layouts.admin')
@section('title', 'Ventes magasin')
@section('content')
<div class="page-head"><div><h1>@icon('receipt') Ventes magasin</h1><div class="sub">Tickets de caisse, retours et factures</div></div>
  @can('retail.sell')<a class="btn btn-accent" href="{{ route('admin.pos') }}">@icon('register') Ouvrir la caisse</a>@endcan</div>
<form class="toolbar">
  <div class="field"><label class="small">Recherche</label><input type="search" name="q" value="{{ request('q') }}" placeholder="N° ticket, client, téléphone"></div>
  <div class="field"><label class="small">Du</label><input type="date" name="from" value="{{ request('from') }}"></div>
  <div class="field"><label class="small">Au</label><input type="date" name="to" value="{{ request('to') }}"></div>
  <div class="field"><label class="small">Caissier</label><select name="cashier"><option value="">Tous</option>@foreach($cashiers as $c)<option value="{{ $c->id }}" @selected(request('cashier') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
  <div class="field"><label class="small">Statut</label><select name="status"><option value="">Tous</option>@foreach(['completed' => 'Payé', 'partially_refunded' => 'Retour partiel', 'refunded' => 'Remboursé'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
  <button class="btn btn-sm">@icon('search') Filtrer</button>
</form>
<div class="card table-wrap" style="padding:0">
<table><thead><tr><th>Ticket</th><th>Date</th><th>Client</th><th>Caissier</th><th>Paiement</th><th>Statut</th><th class="num">Total TTC</th></tr></thead><tbody>
@forelse($sales as $s)
  <tr><td><a href="{{ route('admin.retail.sales.show', $s) }}"><b>{{ $s->number }}</b></a> @if($s->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
    <td>{{ $s->customer?->displayName() ?? '—' }}</td><td>{{ $s->cashier?->name }}</td>
    <td class="small">{{ $s->payments->where('type', 'payment')->map(fn ($p) => __('payment.'.$p->method))->unique()->implode(', ') }}</td>
    <td><span class="pill {{ $s->status === 'completed' ? 'pill-ok' : ($s->status === 'refunded' ? 'pill-err' : 'pill-warn') }}">{{ ['completed' => 'Payé', 'partially_refunded' => 'Retour partiel', 'refunded' => 'Remboursé'][$s->status] }}</span></td>
    <td class="num"><b>{{ money($s->total_ttc) }}</b></td></tr>
@empty<tr><td colspan="7"><div class="empty">@icon('receipt')<div>Aucune vente pour ces critères.</div></div></td></tr>@endforelse
</tbody></table></div>
{{ $sales->links('partials.pagination') }}
@endsection
