@extends('layouts.admin')
@section('title', 'Tableau de bord')
@section('content')
<div class="page-head"><h1>Tableau de bord</h1>
  <form class="toolbar" style="margin:0"><div class="field"><label class="small" for="from">Du</label><input id="from" type="date" name="from" value="{{ $from->toDateString() }}"></div><div class="field"><label class="small" for="to">au</label><input id="to" type="date" name="to" value="{{ $to->toDateString() }}"></div><button class="btn btn-sm">Appliquer</button></form></div>
@if($hasDemo)<div class="alert alert-warn small">Des données de démonstration (marquées <span class="pill pill-demo">DÉMO</span>) sont présentes et incluses dans les chiffres. Supprimez-les avant la mise en production : <code>php artisan hocreative:purge-demo</code>.</div>@endif
<div class="kpis">
  @can('finance.view')
    <div class="kpi"><div class="v">{{ money($k['revenue']) }}</div><div class="l">CA HT ({{ $k['orders'] }} commandes)</div></div>
    <div class="kpi"><div class="v">{{ money($k['gross_margin']) }}</div><div class="l">Marge brute (lignes − coûts réels)</div></div>
    <div class="kpi @if((float) $k['receivables'] > 0) alert-kpi @endif"><div class="v">{{ money($k['receivables']) }}</div><div class="l">Soldes clients à encaisser</div></div>
  @endcan
  <div class="kpi"><div class="v">{{ $k['accepted_quotes'] }}</div><div class="l">Devis acceptés @can('finance.view')({{ money($k['accepted_value']) }})@endcan</div></div>
  <div class="kpi"><div class="v">{{ $k['pending_proofs'] }}</div><div class="l">BAT en attente client</div></div>
  <div class="kpi"><div class="v">{{ $k['artwork_waiting'] }}</div><div class="l">Fichiers clients attendus</div></div>
  <div class="kpi"><div class="v">{{ $k['jobs_today'] }}</div><div class="l">Travaux à livrer aujourd'hui</div></div>
  <div class="kpi @if($k['jobs_overdue']) alert-kpi @endif"><div class="v">{{ $k['jobs_overdue'] }}</div><div class="l">Travaux en retard</div></div>
  <div class="kpi @if($k['stock_alerts']) alert-kpi @endif"><div class="v">{{ $k['stock_alerts'] }}</div><div class="l">Alertes de stock</div></div>
</div>
<div class="split">
  <div class="card table-wrap"><h2 style="font-size:1.1rem">Échéances (aujourd'hui et retards)</h2>
    <table><thead><tr><th>Travail</th><th>Commande</th><th>Étape</th><th>Échéance</th></tr></thead><tbody>
    @forelse($dueJobs as $j)<tr><td><a href="{{ route('admin.production.show', $j) }}">{{ $j->number }}</a><div class="small muted">{{ \Illuminate\Support\Str::limit($j->item->description, 50) }}</div></td><td>{{ $j->order->number }}</td><td><span class="pill">{{ __('stage.'.$j->stage) }}</span></td><td @class(['error-text' => $j->isOverdue()])>{{ d($j->due_at) }}</td></tr>
    @empty<tr><td colspan="4" class="muted">Rien en retard 🎉</td></tr>@endforelse
    </tbody></table></div>
  <div>
    <div class="card"><h2 style="font-size:1.1rem">BAT en attente</h2>
      @forelse($proofs as $i)<p class="small"><a href="{{ route('admin.orders.show', $i->order) }}">{{ $i->order->number }}</a> – {{ \Illuminate\Support\Str::limit($i->description, 50) }}</p>@empty<p class="muted small">Aucun.</p>@endforelse</div>
    <div class="card"><h2 style="font-size:1.1rem">Stock bas</h2>
      @forelse($lowStock as $m)<p class="small"><a href="{{ route('admin.materials.show', $m) }}">{{ $m->name }}</a> – {{ $m->available() }} {{ $m->unit }} (seuil {{ (float) $m->low_stock_threshold }})</p>@empty<p class="muted small">Aucune alerte.</p>@endforelse</div>
  </div>
</div>
<div class="card table-wrap"><h2 style="font-size:1.1rem">Dernières commandes</h2>
  <table><thead><tr><th>N°</th><th>Client</th><th>Canal</th><th>Statut</th><th>Paiement</th>@can('finance.view')<th class="num">Total</th>@endcan</tr></thead><tbody>
  @foreach($recent as $o)<tr><td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a> @if($o->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ $o->customer?->displayName() }}</td><td>{{ __('channel.'.$o->channel) }}</td><td><span class="pill">{{ __('order_status.'.$o->status) }}</span></td><td><span class="pill">{{ __('payment_status.'.$o->payment_status) }}</span></td>@can('finance.view')<td class="num">{{ money($o->total) }}</td>@endcan</tr>@endforeach
  </tbody></table></div>
@endsection
