@extends('layouts.admin')
@section('title', $material->name)
@section('content')
<div class="page-head"><h1>{{ $material->name }} <span class="small muted">{{ $material->sku }}</span></h1><a class="btn btn-sm btn-ghost" href="{{ route('admin.r.edit', ['materials', $material->id]) }}">Modifier la fiche</a></div>
<div class="kpis"><div class="kpi"><div class="v">{{ (float) $material->stock_qty }} {{ $material->unit }}</div><div class="l">En stock</div></div><div class="kpi"><div class="v">{{ (float) $material->reserved_qty }}</div><div class="l">Réservé</div></div>
  <div class="kpi @if($material->isLow()) alert-kpi @endif"><div class="v">{{ (float) $material->available() }}</div><div class="l">Disponible (seuil {{ (float) $material->low_stock_threshold }})</div></div><div class="kpi"><div class="v">{{ money(round((float) $material->unit_cost, 2)) }}</div><div class="l">Coût moyen pondéré</div></div></div>
<div class="split">
  <div class="card table-wrap"><h3>Mouvements</h3><table class="small"><thead><tr><th>Date</th><th>Type</th><th class="num">Qté</th><th class="num">Coût u.</th><th>Travail</th><th>Par</th><th>Note</th></tr></thead><tbody>
    @foreach($movements as $mv)<tr><td>{{ d($mv->created_at, true) }}</td><td>{{ __('movement.'.$mv->type) }}</td><td class="num">{{ (float) $mv->quantity }}</td><td class="num">{{ round((float) $mv->unit_cost, 2) }}</td><td>@if($mv->job)<a href="{{ route('admin.production.show', $mv->job) }}">{{ $mv->job->number }}</a>@endif</td><td>{{ $mv->user?->name }}</td><td>{{ $mv->notes }}</td></tr>@endforeach
  </tbody></table>{{ $movements->links('partials.pagination') }}</div>
  <form class="card" method="post" action="{{ route('admin.materials.adjust', $material) }}" data-once>@csrf
    <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
    <h3>Mouvement manuel</h3>
    <div class="field"><label class="small">Type</label><select name="mode"><option value="receipt">Entrée (réception hors bon de commande)</option><option value="count">Inventaire : fixer le stock à…</option></select></div>
    <div class="field"><label class="small">Quantité</label><input type="number" step="0.001" min="0" name="quantity" required></div>
    <div class="field"><label class="small">Coût unitaire (entrée)</label><input type="number" step="0.0001" min="0" name="unit_cost" value="{{ round((float) $material->unit_cost, 4) }}"></div>
    <div class="field"><label class="small">Motif *</label><input name="reason" required></div>
    <button class="btn btn-sm">Enregistrer</button><p class="help">Les ajustements d'inventaire sont tracés dans le journal d'audit.</p>
  </form>
</div>
@endsection
