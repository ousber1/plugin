@extends('layouts.admin')
@section('title', 'Stock')
@section('content')
<div class="page-head"><h1>Stock</h1><div class="flex"><a class="btn btn-sm btn-ghost" href="{{ route('admin.r.create', 'materials') }}">+ Matière</a><a class="btn btn-sm" href="{{ route('admin.po.create') }}">+ Bon de commande</a></div></div>
<div class="card table-wrap"><table><thead><tr><th>Réf.</th><th>Matière</th><th>Catégorie</th><th class="num">Stock</th><th class="num">Réservé</th><th class="num">Disponible</th><th class="num">Seuil</th><th class="num">Coût moyen</th><th>Fournisseur</th></tr></thead><tbody>
@foreach($materials as $m)<tr @if($m->isLow()) style="background:#FFF7F7" @endif><td>{{ $m->sku }}</td><td><a href="{{ route('admin.materials.show', $m) }}">{{ $m->name }}</a> @if($m->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ $m->category }}</td>
  <td class="num">{{ (float) $m->stock_qty }} {{ $m->unit }}</td><td class="num">{{ (float) $m->reserved_qty }}</td><td class="num"><strong>{{ (float) $m->available() }}</strong> @if($m->isLow())⚠@endif</td><td class="num">{{ (float) $m->low_stock_threshold }}</td><td class="num">{{ money(round((float) $m->unit_cost, 2)) }}</td><td>{{ $m->supplier?->name }}</td></tr>@endforeach
</tbody></table></div>
@endsection
