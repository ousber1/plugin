@extends('layouts.admin')
@section('title', 'Fournisseurs')
@section('content')
<div class="page-head"><div><h1>@icon('store') Fournisseurs</h1><div class="sub">Achats de marchandises et de matières, règlements et soldes</div></div>
  <div class="flex"><a class="btn btn-ghost" href="{{ route('admin.po.create') }}">@icon('box') Bon de commande</a><a class="btn btn-accent" href="{{ route('admin.r.create', 'suppliers') }}">@icon('plus') Nouveau fournisseur</a></div></div>
<div class="card table-wrap" style="padding:0"><table><thead><tr><th>Fournisseur</th><th>Contact</th><th class="num">Bons de commande</th><th class="num">Reçu HT</th><th class="num">Réglé</th><th class="num">Reste à payer</th></tr></thead><tbody>
@forelse($suppliers as [$s, $b])<tr><td><a href="{{ route('admin.suppliers.show', $s) }}"><b>{{ $s->name }}</b></a> @if($s->is_demo)<span class="pill pill-demo">DÉMO</span>@endif<div class="tiny muted">{{ $s->city }}</div></td><td class="small">{{ $s->contact_name }}<br>{{ $s->phone }}</td><td class="num">{{ $s->purchase_orders_count }}</td><td class="num">{{ money($b['received']) }}</td><td class="num">{{ money($b['paid']) }}</td><td class="num">@if((float) $b['due'] >= 0)<span class="pill {{ (float) $b['due'] > 0 ? 'pill-warn' : 'pill-ok' }}">{{ money($b['due']) }}</span>@else<span class="pill pill-ok">Avance {{ money(abs((float) $b['due'])) }}</span>@endif</td></tr>
@empty<tr><td colspan="6"><div class="empty">@icon('store')<div>Aucun fournisseur.</div></div></td></tr>@endforelse
</tbody></table></div>
@endsection
