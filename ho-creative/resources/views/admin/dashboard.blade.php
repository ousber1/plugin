@extends('layouts.admin')
@section('title', 'Tableau de bord')
@section('content')
@php($fin = auth()->user()->can('finance.view'))
<div class="page-head">
  <div><h1>Bonjour {{ explode(' ', auth()->user()->name)[0] }} 👋</h1><div class="sub">{{ \Illuminate\Support\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }} · période du {{ d($from) }} au {{ d($to) }}</div></div>
  <form class="flex" style="margin:0">
    <input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="Du" style="width:auto">
    <input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="Au" style="width:auto">
    <button class="btn btn-sm">Appliquer</button>
  </form>
</div>
@if($hasDemo)<div class="alert alert-warn">@icon('alert')<div>Des données de <b>démonstration</b> (badge <span class="pill pill-demo">DÉMO</span>) sont incluses dans les chiffres. Supprimez-les avant l'ouverture : <code>php artisan hocreative:purge-demo</code>.</div></div>@endif

<h2 style="font-size:1rem;margin:4px 0 10px" class="muted">Magasin</h2>
<div class="kpis">
  @can('retail.view')
  <a class="kpi k-accent" href="{{ route('admin.retail.sales.index', ['from' => today()->toDateString()]) }}"><div class="k-ic">@icon('register')</div><div><div class="v">{{ money($retail['today_ttc']) }}</div><div class="l">Ventes magasin aujourd'hui · {{ $retail['today_count'] }} ticket(s)</div></div></a>
  <a class="kpi k-ink" href="{{ route('admin.retail.sales.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"><div class="k-ic">@icon('receipt')</div><div><div class="v">{{ money($retail['net_ttc']) }}</div><div class="l">Ventes magasin nettes TTC ({{ $retail['count'] }} tickets)</div></div></a>
  @endcan
  @if($fin)<div class="kpi k-ok"><div class="k-ic">@icon('chart')</div><div><div class="v">{{ money($retail['margin']) }}</div><div class="l">Marge brute magasin HT</div></div></div>@endif
  @can('retail.view')
  <a class="kpi {{ $retail['low_stock'] ? 'alert-kpi' : 'k-ok' }}" href="{{ route('admin.retail.products.index', ['low' => 1]) }}"><div class="k-ic">@icon('tag')</div><div><div class="v">{{ $retail['low_stock'] }}</div><div class="l">Articles en stock bas</div></div></a>
  @endcan
  @can('retail.cash')<a class="kpi k-warn" href="{{ route('admin.retail.sessions.index') }}"><div class="k-ic">@icon('cash')</div><div><div class="v">{{ $retail['open_sessions'] }}</div><div class="l">Caisse(s) ouverte(s)</div></div></a>@endcan
</div>

<h2 style="font-size:1rem;margin:10px 0 10px" class="muted">Imprimerie</h2>
<div class="kpis">
  @if($fin)
    <div class="kpi k-accent"><div class="k-ic">@icon('bag')</div><div><div class="v">{{ money($k['revenue']) }}</div><div class="l">CA HT · {{ $k['orders'] }} commande(s)</div></div></div>
    <div class="kpi k-ok"><div class="k-ic">@icon('chart')</div><div><div class="v">{{ money($k['gross_margin']) }}</div><div class="l">Marge brute (lignes − coûts réels)</div></div></div>
    <a class="kpi {{ (float) $k['receivables'] > 0 ? 'alert-kpi' : 'k-ok' }}" href="{{ route('admin.reports.index', ['report' => 'receivables']) }}"><div class="k-ic">@icon('wallet')</div><div><div class="v">{{ money($k['receivables']) }}</div><div class="l">Soldes clients à encaisser</div></div></a>
  @endif
  <a class="kpi k-ink" href="{{ route('admin.quotes.index', ['status' => 'accepted']) }}"><div class="k-ic">@icon('quote')</div><div><div class="v">{{ $k['accepted_quotes'] }}</div><div class="l">Devis acceptés @if($fin)· {{ money($k['accepted_value']) }}@endif</div></div></a>
  <div class="kpi k-warn"><div class="k-ic">@icon('eye')</div><div><div class="v">{{ $k['pending_proofs'] }}</div><div class="l">BAT en attente client</div></div></div>
  <div class="kpi"><div class="k-ic">@icon('upload')</div><div><div class="v">{{ $k['artwork_waiting'] }}</div><div class="l">Fichiers clients attendus</div></div></div>
  <a class="kpi k-ink" href="{{ route('admin.production.board') }}"><div class="k-ic">@icon('factory')</div><div><div class="v">{{ $k['jobs_today'] }}</div><div class="l">Travaux à livrer aujourd'hui</div></div></a>
  <a class="kpi {{ $k['jobs_overdue'] ? 'alert-kpi' : 'k-ok' }}" href="{{ route('admin.production.list') }}"><div class="k-ic">@icon('clock')</div><div><div class="v">{{ $k['jobs_overdue'] }}</div><div class="l">Travaux en retard</div></div></a>
  <a class="kpi {{ $k['stock_alerts'] ? 'alert-kpi' : 'k-ok' }}" href="{{ route('admin.stock.index') }}"><div class="k-ic">@icon('warehouse')</div><div><div class="v">{{ $k['stock_alerts'] }}</div><div class="l">Alertes matières atelier</div></div></a>
</div>

<div class="split">
  <div>
    @can('retail.view')
    <div class="card">
      <div class="card-head"><h2>Ventes magasin – 14 derniers jours (TTC)</h2><a class="small" href="{{ route('admin.reports.index', ['report' => 'retail_daily']) }}">Rapport →</a></div>
      @php($max = max(1, max($series)))
      <div class="bar-chart" role="img" aria-label="Ventes magasin par jour">
        @foreach($series as $day => $v)<div style="height:{{ max(2, round($v / $max * 100)) }}%" title="{{ d($day) }} : {{ money($v) }}"><span>{{ \Illuminate\Support\Carbon::parse($day)->format('d') }}</span></div>@endforeach
      </div>
      <div style="height:22px"></div>
    </div>
    @endcan
    <div class="card table-wrap">
      <div class="card-head"><h2>Échéances atelier (aujourd'hui et retards)</h2><a class="small" href="{{ route('admin.production.board') }}">Kanban →</a></div>
      <table><thead><tr><th>Travail</th><th>Commande</th><th>Étape</th><th>Échéance</th></tr></thead><tbody>
      @forelse($dueJobs as $j)<tr><td><a href="{{ route('admin.production.show', $j) }}"><b>{{ $j->number }}</b></a><div class="tiny muted">{{ \Illuminate\Support\Str::limit($j->item->description, 52) }}</div></td><td>{{ $j->order->number }}</td><td><span class="pill pill-info">{{ __('stage.'.$j->stage) }}</span></td><td @class(['error-text' => $j->isOverdue()])>{{ d($j->due_at) }}</td></tr>
      @empty<tr><td colspan="4"><div class="empty">@icon('check')<div>Aucun travail en retard.</div></div></td></tr>@endforelse
      </tbody></table>
    </div>
    <div class="card table-wrap">
      <div class="card-head"><h2>Dernières commandes</h2><a class="small" href="{{ route('admin.orders.index') }}">Toutes →</a></div>
      <table><thead><tr><th>N°</th><th>Client</th><th>Canal</th><th>Statut</th><th>Paiement</th>@if($fin)<th class="num">Total</th>@endif</tr></thead><tbody>
      @forelse($recent as $o)<tr><td><a href="{{ route('admin.orders.show', $o) }}"><b>{{ $o->number }}</b></a> @if($o->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ $o->customer?->displayName() }}</td><td>{{ __('channel.'.$o->channel) }}</td><td><span class="pill">{{ __('order_status.'.$o->status) }}</span></td><td><span class="pill {{ $o->payment_status === 'paid' ? 'pill-ok' : ($o->payment_status === 'unpaid' ? 'pill-warn' : 'pill-info') }}">{{ __('payment_status.'.$o->payment_status) }}</span></td>@if($fin)<td class="num">{{ money($o->total) }}</td>@endif</tr>
      @empty<tr><td colspan="6"><div class="empty">Aucune commande.</div></td></tr>@endforelse
      </tbody></table>
    </div>
  </div>
  <div>
    @can('retail.sell')
    <a class="card" href="{{ route('admin.pos') }}" style="display:flex;gap:14px;align-items:center;text-decoration:none;background:linear-gradient(135deg,var(--accent),#7A1FA2);color:#fff;border:0">
      <div class="k-ic" style="width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,.18);display:grid;place-items:center">@icon('register')</div>
      <div><b style="font-size:1.1rem">Caisse du magasin</b><div class="small" style="opacity:.85">Encaisser une vente, scanner un code-barres</div></div>
    </a>
    @endcan
    @can('retail.view')
    <div class="card"><div class="card-head"><h3>Articles à réapprovisionner</h3><a class="small" href="{{ route('admin.po.create') }}">Commander →</a></div>
      @forelse($lowRetail as $p)<div class="flex between small" style="padding:6px 0;border-bottom:1px solid var(--line-2)"><a href="{{ route('admin.retail.products.show', $p) }}">{{ $p->name }}</a><span class="pill pill-err">{{ (float) $p->stock_qty }} / {{ (float) $p->low_stock_threshold }}</span></div>@empty<p class="muted small">Aucun article sous le seuil.</p>@endforelse</div>
    @endcan
    <div class="card"><div class="card-head"><h3>BAT en attente</h3></div>
      @forelse($proofs as $i)<div class="small" style="padding:6px 0;border-bottom:1px solid var(--line-2)"><a href="{{ route('admin.orders.show', $i->order) }}"><b>{{ $i->order->number }}</b></a> – {{ \Illuminate\Support\Str::limit($i->description, 46) }}</div>@empty<p class="muted small">Aucun BAT en attente.</p>@endforelse</div>
    <div class="card"><div class="card-head"><h3>Matières atelier en stock bas</h3></div>
      @forelse($lowStock as $m)<div class="flex between small" style="padding:6px 0;border-bottom:1px solid var(--line-2)"><a href="{{ route('admin.materials.show', $m) }}">{{ $m->name }}</a><span class="pill pill-err">{{ $m->available() }} {{ $m->unit }}</span></div>@empty<p class="muted small">Aucune alerte.</p>@endforelse</div>
  </div>
</div>
@endsection
