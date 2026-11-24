@extends('layouts.admin')
@section('title', 'Sessions de caisse')
@section('content')
<div class="page-head"><div><h1>@icon('cash') Sessions de caisse</h1><div class="sub">Ouverture, fond de caisse, clôture et écarts</div></div><a class="btn btn-accent" href="{{ route('admin.pos') }}">@icon('register') Caisse</a></div>
<div class="card table-wrap" style="padding:0"><table><thead><tr><th>Caisse</th><th>Ouverture</th><th>Clôture</th><th class="num">Tickets</th><th class="num">Fond</th><th class="num">Attendu</th><th class="num">Compté</th><th class="num">Écart</th><th>Statut</th></tr></thead><tbody>
@forelse($sessions as $s)<tr><td><a href="{{ route('admin.retail.sessions.show', $s) }}"><b>{{ $s->register }} #{{ $s->id }}</b></a></td><td>{{ $s->opened_at->format('d/m/Y H:i') }}<div class="tiny muted">{{ $s->opener?->name }}</div></td><td>{{ $s->closed_at?->format('d/m/Y H:i') ?? '—' }}<div class="tiny muted">{{ $s->closer?->name }}</div></td>
  <td class="num">{{ $s->sales_count }}</td><td class="num">{{ money($s->opening_float) }}</td><td class="num">{{ $s->expected_cash !== null ? money($s->expected_cash) : '—' }}</td><td class="num">{{ $s->counted_cash !== null ? money($s->counted_cash) : '—' }}</td>
  <td class="num">@if($s->difference !== null)<span class="pill {{ (float) $s->difference == 0 ? 'pill-ok' : 'pill-err' }}">{{ money($s->difference) }}</span>@else — @endif</td>
  <td><span class="pill {{ $s->status === 'open' ? 'pill-warn' : 'pill-ok' }}">{{ $s->status === 'open' ? 'Ouverte' : 'Clôturée' }}</span></td></tr>
@empty<tr><td colspan="9"><div class="empty">@icon('cash')<div>Aucune session. Ouvrez la caisse pour commencer.</div></div></td></tr>@endforelse
</tbody></table></div>{{ $sessions->links('partials.pagination') }}
@endsection
