@extends('layouts.admin')
@section('title', 'Articles du magasin')
@section('content')
<div class="page-head"><div><h1>@icon('tag') Articles & stock du magasin</h1><div class="sub">{{ $products->total() }} article(s) · valeur du stock {{ money($stockValue) }} HT @if($lowCount)· <a href="?low=1" class="error-text">{{ $lowCount }} en stock bas</a>@endif</div></div>
  <div class="flex">@can('retail.manage')<a class="btn btn-ghost" href="{{ route('admin.r.index', 'retail_categories') }}">@icon('layers') Rayons</a><a class="btn btn-ghost" href="{{ route('admin.po.create') }}">@icon('box') Commander</a><a class="btn btn-accent" href="{{ route('admin.retail.products.create') }}">@icon('plus') Nouvel article</a>@endcan</div></div>
<form class="toolbar">
  <div class="field grow"><label class="small">Recherche</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, référence ou code-barres"></div>
  <div class="field"><label class="small">Rayon</label><select name="category"><option value="">Tous</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
  <label class="check small" style="margin-bottom:8px"><input type="checkbox" name="low" value="1" @checked(request()->boolean('low'))> Stock bas uniquement</label>
  <button class="btn btn-sm">@icon('search') Filtrer</button>
</form>
<div class="card table-wrap" style="padding:0"><table>
<thead><tr><th></th><th>Article</th><th>Rayon</th><th class="num">Prix TTC</th><th class="num">Coût HT</th><th class="num">Marge</th><th class="num">Stock</th><th>Fournisseur</th></tr></thead><tbody>
@forelse($products as $p)
  <tr @if(! $p->is_active) style="opacity:.55" @endif>
    <td>@if($p->image)<img class="thumb-sm" src="{{ $p->image }}" alt="">@else<span class="thumb-sm">@icon('tag')</span>@endif</td>
    <td><a href="{{ route('admin.retail.products.show', $p) }}"><b>{{ $p->name }}</b></a> @if($p->is_demo)<span class="pill pill-demo">DÉMO</span>@endif<div class="tiny muted">{{ $p->sku }} {{ $p->barcode ? '· '.$p->barcode : '' }}</div></td>
    <td>@if($p->category)<span class="pill" style="background:{{ $p->category->color }}1A;color:{{ $p->category->color }}">{{ $p->category->name }}</span>@endif</td>
    <td class="num"><b>{{ money($p->price_ttc) }}</b></td><td class="num">{{ (float) $p->cost_price ? money(round((float) $p->cost_price, 2)) : '—' }}</td>
    <td class="num">{{ $p->marginPct() !== null ? $p->marginPct().' %' : '—' }}</td>
    <td class="num">@if($p->track_stock)<span class="pill {{ $p->isLow() ? 'pill-err' : 'pill-ok' }}">{{ (float) $p->stock_qty }} {{ $p->unit !== 'piece' ? $p->unit : '' }}</span>@else <span class="muted tiny">non suivi</span>@endif</td>
    <td class="small">{{ $p->supplier?->name }}</td></tr>
@empty<tr><td colspan="8"><div class="empty">@icon('tag')<div>Aucun article. Créez votre premier article pour vendre en caisse.</div></div></td></tr>@endforelse
</tbody></table></div>{{ $products->links('partials.pagination') }}
@endsection
