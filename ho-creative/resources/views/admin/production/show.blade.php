@extends('layouts.admin')
@section('title', $job->number)
@section('content')
@php($item = $job->item)
<div class="page-head"><h1>{{ $job->number }} <span class="pill">{{ __('stage.'.$job->stage) }}</span></h1>
  <div class="flex"><a class="btn btn-sm btn-ghost" href="{{ route('admin.production.sheet', $job) }}" target="_blank">🖨 Fiche de production</a><a class="btn btn-sm btn-ghost" href="{{ route('admin.orders.show', $job->order) }}">Commande {{ $job->order->number }}</a></div></div>
@if($blockers)<div class="alert alert-warn">⛔ Impression bloquée : {{ implode(' ', $blockers) }}</div>@endif
<div class="split">
  <div>
    <div class="card">
      <h3>{{ $item->description }}</h3>
      <dl class="dl"><dt>Quantité</dt><dd>{{ $item->quantity }}</dd>
        @if($item->width_mm)<dt>Format fini</dt><dd>{{ (float) $item->width_mm }} × {{ (float) $item->height_mm }} mm</dd>@endif
        @foreach($item->options_snapshot['options'] ?? [] as $o)<dt>{{ $o['label'] }}</dt><dd>{{ $o['value_label'] }}</dd>@endforeach
        <dt>Client</dt><dd>{{ $job->order->customer?->displayName() }}</dd><dt>Fichier</dt><dd>{{ __('artwork_mode.'.$item->artwork_mode) }} · {{ $item->artwork_status }}</dd>
        <dt>BAT</dt><dd>{{ $item->requires_proof ? __('proof_status.'.($item->proof_status)) : 'non requis' }} @if($p = $item->proofs->sortByDesc('version')->first()) (v{{ $p->version }})@endif</dd>
        @if($item->remakeOf)<dt>Reprise de</dt><dd>{{ $item->remakeOf->description }} – {{ $item->remake_reason }}</dd>@endif
      </dl>
      @can('production.manage')
      <form method="post" action="{{ route('admin.production.transition', $job) }}" class="flex" style="margin-top:12px">@csrf
        <select name="stage" style="width:auto">@foreach($stages as $s)<option value="{{ $s }}" @selected($job->stage === $s)>{{ __('stage.'.$s) }}</option>@endforeach</select>
        <input name="note" placeholder="Note (obligatoire pour annuler)" style="flex:1"><button class="btn btn-sm">Changer d'étape</button></form>
      @endcan
    </div>
    <div class="card">
      <h3>Matières</h3>
      <table class="small"><thead><tr><th>Date</th><th>Matière</th><th>Mouvement</th><th class="num">Qté</th></tr></thead><tbody>
      @forelse($job->stockMovements->sortByDesc('id') as $m)<tr><td>{{ d($m->created_at, true) }}</td><td>{{ $m->material->name }}</td><td>{{ __('movement.'.$m->type) }}</td><td class="num">{{ (float) $m->quantity }} {{ $m->material->unit }}</td></tr>@empty<tr><td colspan="4" class="muted">Aucun mouvement.</td></tr>@endforelse
      </tbody></table>
      @can('stock.consume')
      <form method="post" action="{{ route('admin.production.material', $job) }}" class="flex" style="margin-top:8px" data-once>@csrf
        <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <select name="material_id" style="flex:2">@foreach($materials as $m)<option value="{{ $m->id }}">{{ $m->name }} (dispo {{ $m->available() }} {{ $m->unit }})</option>@endforeach</select>
        <select name="action" style="width:auto"><option value="reserve">Réserver</option><option value="consume">Consommer</option><option value="waste">Gâche</option><option value="release">Libérer réservation</option></select>
        <input type="number" step="0.001" min="0.001" name="quantity" required placeholder="Qté" style="width:110px"><input name="notes" placeholder="Note" style="flex:1"><button class="btn btn-sm">OK</button>
      </form>
      @endcan
    </div>
    @can('production.manage')
    <div class="card">
      <h3>Coûts</h3>
      <table class="small"><thead><tr><th>Type</th><th>Description</th><th class="num">Qté</th><th class="num">Coût unit.</th><th class="num">Total</th><th></th></tr></thead><tbody>
        @foreach($job->costs as $c)<tr><td>{{ __('cost.'.$c->type) }}</td><td>{{ $c->description }}</td><td class="num">{{ (float) $c->quantity }}</td><td class="num">{{ money(round((float) $c->unit_cost, 2)) }}</td><td class="num">{{ money($c->total) }}</td><td>{{ $c->is_estimate ? 'estimé' : 'réel' }}</td></tr>@endforeach
      </tbody></table>
      <form method="post" action="{{ route('admin.production.cost', $job) }}" class="flex" style="margin-top:8px" data-once>@csrf
        <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <select name="type" style="width:auto"><option value="labor">Main-d'œuvre</option><option value="setup">Calage</option><option value="machine">Temps machine</option><option value="outsourced">Sous-traitance</option><option value="other">Autre</option></select>
        <input name="description" required placeholder="Description" style="flex:1"><input type="number" step="0.001" name="quantity" value="1" style="width:80px" title="Quantité / heures"><input type="number" step="0.01" name="unit_cost" required placeholder="Coût unit." style="width:110px">
        <label class="check small"><input type="checkbox" name="is_estimate" value="1"> estimé</label><button class="btn btn-sm">Ajouter</button></form>
      @can('finance.view')
        <p class="small" style="margin-top:8px">Prix HT : <strong>{{ money($summary['revenue']) }}</strong> · Coût estimé : {{ money($summary['estimated']) }} · Coût réel : {{ money($summary['actual']) }} · Marge brute : <strong>{{ money($summary['margin']) }}</strong> {{ $summary['margin_pct'] !== null ? '('.$summary['margin_pct'].' %)' : '' }}</p>
      @endcan
    </div>
    @endcan
  </div>
  <div>
    @can('production.manage')
    <form class="card" method="post" action="{{ route('admin.production.update', $job) }}">@csrf @method('put')
      <h3>Planification</h3>
      <div class="field"><label class="small">Opérateur</label><select name="assigned_to"><option value="">—</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($job->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
      <div class="field"><label class="small">Atelier</label><select name="department">@foreach(['digital' => 'Numérique', 'offset' => 'Offset', 'large_format' => 'Grand format', 'finishing' => 'Façonnage', 'packaging' => 'Packaging', 'studio' => 'Studio'] as $k => $v)<option value="{{ $k }}" @selected($job->department === $k)>{{ $v }}</option>@endforeach</select></div>
      <div class="field"><label class="small">Machine</label><select name="machine_id"><option value="">—</option>@foreach($machines as $m)<option value="{{ $m->id }}" @selected($job->machine_id === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
      <div class="field"><label class="small">Échéance</label><input type="date" name="due_at" value="{{ $job->due_at?->toDateString() }}"></div>
      <div class="field"><label class="small">Priorité</label><select name="priority">@foreach(['low', 'normal', 'high', 'urgent'] as $p)<option value="{{ $p }}" @selected($job->priority === $p)>{{ $p }}</option>@endforeach</select></div>
      <div class="field"><label class="small">Notes internes</label><textarea name="internal_notes">{{ $job->internal_notes }}</textarea></div>
      <button class="btn btn-sm">Enregistrer</button>
    </form>
    @endcan
    <div class="card"><h3>Fichiers</h3>
      @foreach($item->artworkFiles->sortByDesc('id') as $f)<p class="small"><a href="{{ route('files.artwork', $f) }}" target="_blank">{{ $f->side }} v{{ $f->version }} – {{ $f->original_name }}</a> ({{ $f->review_status }})</p>@endforeach
      @if($item->design && $item->design->exports)<p class="small"><a href="{{ route('files.design', [$item->design, 'pdf']) }}" target="_blank">Design en ligne (PDF)</a></p>@endif
      @foreach($item->proofs->sortByDesc('version') as $p)<p class="small"><a href="{{ route('files.proof', $p) }}" target="_blank">BAT v{{ $p->version }}</a> – {{ __('proof_status.'.$p->status) }}</p>@endforeach
    </div>
    <div class="card"><h3>Historique</h3>
      @foreach($job->events->sortByDesc('id') as $e)<p class="small">{{ d($e->created_at, true) }} – {{ $e->user?->name ?? 'Système' }} : {{ $e->from_stage ? __('stage.'.$e->from_stage).' → ' : '' }}<strong>{{ __('stage.'.$e->to_stage) }}</strong> {{ $e->note ? '– '.$e->note : '' }}</p>@endforeach
    </div>
  </div>
</div>
@endsection
