@extends('layouts.admin')
@section('title', 'Session '.$session->id)
@section('content')
<div class="page-head"><div><h1>@icon('cash') {{ $session->register }} – session #{{ $session->id }} <span class="pill {{ $session->status === 'open' ? 'pill-warn' : 'pill-ok' }}">{{ $session->status === 'open' ? 'Ouverte' : 'Clôturée' }}</span></h1>
  <div class="sub">Ouverte le {{ $session->opened_at->format('d/m/Y à H:i') }} par {{ $session->opener?->name }} @if($session->closed_at)· clôturée le {{ $session->closed_at->format('d/m/Y à H:i') }} par {{ $session->closer?->name }}@endif</div></div>
  <button class="btn btn-ghost" onclick="print()">@icon('printer') Imprimer (rapport Z)</button></div>
<div class="kpis">
  <div class="kpi k-accent"><div class="k-ic">@icon('receipt')</div><div><div class="v">{{ money($summary['sales_total']) }}</div><div class="l">{{ $summary['sales_count'] }} ticket(s) TTC</div></div></div>
  <div class="kpi k-ink"><div class="k-ic">@icon('cash')</div><div><div class="v">{{ money($summary['expected']) }}</div><div class="l">Espèces attendues dans le tiroir</div></div></div>
  @if($session->status === 'closed')
    <div class="kpi {{ (float) $session->difference == 0 ? 'k-ok' : 'alert-kpi' }}"><div class="k-ic">@icon('check')</div><div><div class="v">{{ money($session->difference) }}</div><div class="l">Écart (compté {{ money($session->counted_cash) }})</div></div></div>
  @endif
</div>
<div class="split">
  <div>
    <div class="card table-wrap"><div class="card-head"><h3>Récapitulatif par moyen de paiement</h3></div>
      <table><thead><tr><th>Moyen</th><th class="num">Encaissé</th><th class="num">Remboursé</th><th class="num">Net</th></tr></thead><tbody>
      @foreach($summary['by_method'] as $m => $v)<tr><td>{{ __('payment.'.$m) }}</td><td class="num">{{ money($v['payments']) }}</td><td class="num">{{ money($v['refunds']) }}</td><td class="num"><b>{{ money((float) $v['payments'] - (float) $v['refunds']) }}</b></td></tr>@endforeach
      </tbody></table>
      <dl class="dl" style="margin-top:14px"><dt>Fond de caisse</dt><dd>{{ money($summary['float']) }}</dd><dt>+ ventes espèces</dt><dd>{{ money($summary['cash_sales']) }}</dd><dt>− remboursements espèces</dt><dd>{{ money($summary['cash_refunds']) }}</dd><dt>+ entrées</dt><dd>{{ money($summary['cash_in']) }}</dd><dt>− sorties</dt><dd>{{ money($summary['cash_out']) }}</dd><dt><b>= attendu</b></dt><dd><b>{{ money($summary['expected']) }}</b></dd></dl>
    </div>
    <div class="card table-wrap"><div class="card-head"><h3>Tickets</h3></div><table><tbody>
      @forelse($sales as $s)<tr><td><a href="{{ route('admin.retail.sales.show', $s) }}">{{ $s->number }}</a></td><td>{{ $s->created_at->format('H:i') }}</td><td>{{ $s->cashier?->name }}</td><td class="num">{{ money($s->total_ttc) }}</td></tr>@empty<tr><td class="muted">Aucune vente.</td></tr>@endforelse
    </tbody></table></div>
  </div>
  <div class="no-print">
    @if($session->status === 'open')
      <form class="card" method="post" action="{{ route('admin.retail.sessions.movement', $session) }}" data-once>@csrf
        <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="card-head"><h3>Entrée / sortie d'espèces</h3></div>
        <div class="grid-2"><select name="type"><option value="out">Sortie (achat, dépôt banque…)</option><option value="in">Entrée (appoint)</option></select><input type="number" step="0.01" min="0.01" name="amount" placeholder="Montant" required></div>
        <input name="reason" placeholder="Motif" required style="margin-top:8px"><button class="btn btn-sm" style="margin-top:8px">Enregistrer</button>
      </form>
      <form class="card" method="post" action="{{ route('admin.retail.sessions.close', $session) }}" data-confirm="Clôturer la caisse ? Aucune vente ne pourra plus y être enregistrée.">@csrf
        <div class="card-head"><h3>Clôturer la caisse</h3></div>
        <p class="small muted">Comptez les espèces du tiroir. L'écart avec le montant attendu ({{ money($summary['expected']) }}) est enregistré.</p>
        <div class="field"><label>Espèces comptées (DH)</label><input type="number" step="0.01" min="0" name="counted_cash" required style="font-size:1.2rem;font-weight:700"></div>
        <div class="field"><label>Remarques</label><textarea name="notes" rows="2"></textarea></div>
        <button class="btn btn-accent btn-block">Clôturer</button>
      </form>
    @endif
    <div class="card"><div class="card-head"><h3>Mouvements d'espèces</h3></div>
      @forelse($session->movements as $m)<div class="flex between small" style="padding:5px 0;border-bottom:1px solid var(--line-2)"><span>{{ $m->type === 'in' ? 'Entrée' : 'Sortie' }} – {{ $m->reason }} <span class="muted">({{ $m->user?->name }})</span></span><b>{{ $m->type === 'out' ? '−' : '+' }}{{ money($m->amount) }}</b></div>@empty<p class="muted small">Aucun.</p>@endforelse
      @if($session->notes)<p class="small">{{ $session->notes }}</p>@endif
    </div>
  </div>
</div>
@endsection
