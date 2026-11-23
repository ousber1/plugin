@extends('layouts.admin')
@section('title', 'Produits')
@section('content')
<div class="page-head"><h1>Produits & prix</h1>@can('catalog.manage')<a class="btn btn-sm" href="{{ route('admin.products.create') }}">+ Nouveau produit</a>@endcan</div>
<form class="toolbar"><div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, référence…"></div>
  <div class="field"><select name="mode"><option value="">Tous les modes</option>@foreach(\App\Models\Product::MODES as $m)<option value="{{ $m }}" @selected(request('mode') === $m)>{{ __('sale_mode.'.$m) }}</option>@endforeach</select></div><button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table>
  <thead><tr><th>Produit</th><th>Catégorie</th><th>Mode</th><th>Grille publiée</th><th class="num">À partir de</th><th></th></tr></thead>
  <tbody>@foreach($products as $p)
    <tr><td><strong>{{ $p->t('name') }}</strong> @if($p->is_demo)<span class="pill pill-demo">DÉMO</span>@endif<div class="small muted">{{ $p->slug }}</div></td><td>{{ $p->category->t('name') }}</td>
      <td><span class="pill {{ $p->sale_mode === 'instant' ? 'pill-ok' : ($p->sale_mode === 'quote' ? 'pill-accent' : '') }}">{{ __('sale_mode.'.$p->sale_mode) }}</span></td>
      <td>{{ $p->publishedRuleSet ? 'v'.$p->publishedRuleSet->version : '—' }}</td><td class="num">{{ $p->from_price !== null ? money($p->from_price) : '—' }}</td>
      <td class="right" style="white-space:nowrap">@can('catalog.manage')<a class="btn btn-sm btn-ghost" href="{{ route('admin.products.edit', $p) }}">Fiche</a>@endcan @can('pricing.manage')<a class="btn btn-sm btn-ghost" href="{{ route('admin.pricing.edit', $p) }}">Options & prix</a>@endcan <a class="btn btn-sm btn-ghost" href="{{ route('product', $p->slug) }}" target="_blank">↗</a></td></tr>
  @endforeach</tbody>
</table>{{ $products->links('partials.pagination') }}</div>
@endsection
