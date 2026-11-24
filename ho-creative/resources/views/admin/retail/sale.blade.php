@extends('layouts.admin')
@section('title', $sale->number)
@section('content')
@php($inv = $sale->documents->firstWhere('type', 'invoice'))
<div class="page-head"><div><h1>@icon('receipt') Ticket {{ $sale->number }} <span class="pill {{ $sale->status === 'completed' ? 'pill-ok' : ($sale->status === 'refunded' ? 'pill-err' : 'pill-warn') }}">{{ ['completed' => 'Payé', 'partially_refunded' => 'Retour partiel', 'refunded' => 'Remboursé'][$sale->status] }}</span></h1>
  <div class="sub">{{ $sale->created_at->format('d/m/Y à H:i') }} · {{ $sale->session?->register }} · caissier {{ $sale->cashier?->name }}</div></div>
  <div class="flex"><a class="btn btn-ghost" href="{{ route('admin.retail.ticket', $sale) }}" target="_blank">@icon('printer') Ticket</a>
    @if($inv)<a class="btn btn-ghost" href="{{ route('files.document', $inv) }}" target="_blank">@icon('file') Facture {{ $inv->number }}</a>@endif</div></div>
<div class="split">
  <div>
    <div class="card table-wrap" style="padding:0">
      <table><thead><tr><th>Article</th><th class="num">Qté</th><th class="num">P.U. TTC</th><th class="num">Remise</th><th class="num">TVA</th><th class="num">Total TTC</th></tr></thead><tbody>
      @foreach($sale->items as $i)<tr><td><b>{{ $i->name }}</b><div class="tiny muted">{{ $i->sku }} @if((float) $i->returned_qty > 0)· <span class="error-text">retourné {{ (float) $i->returned_qty }}</span>@endif</div></td><td class="num">{{ (float) $i->quantity }}</td><td class="num">{{ money($i->unit_price_ttc) }}</td><td class="num">{{ (float) $i->discount ? money($i->discount) : '—' }}</td><td class="num">{{ (float) $i->tax_rate }} %</td><td class="num"><b>{{ money($i->total_ttc) }}</b></td></tr>@endforeach
      </tbody></table>
      <div style="padding:14px 18px;max-width:340px;margin-inline-start:auto">
        <div class="flex between small"><span class="muted">Total HT</span><span>{{ money($sale->subtotal_ht) }}</span></div>
        <div class="flex between small"><span class="muted">TVA</span><span>{{ money($sale->tax_total) }}</span></div>
        <div class="flex between" style="font-size:1.2rem;font-weight:800;margin-top:4px"><span>Total TTC</span><span>{{ money($sale->total_ttc) }}</span></div>
        @if((float) $sale->refunded_total > 0)<div class="flex between small error-text"><span>Remboursé</span><span>−{{ money($sale->refunded_total) }}</span></div>@endif
        @if($sale->discount_reason)<p class="tiny muted">Remise : {{ $sale->discount_reason }}</p>@endif
      </div>
    </div>
    <div class="card"><div class="card-head"><h3>Paiements & remboursements</h3></div>
      @foreach($sale->payments as $p)<div class="flex between small" style="padding:6px 0;border-bottom:1px solid var(--line-2)"><span>{{ $p->type === 'refund' ? '↩ Remboursement' : '✔ Paiement' }} – {{ __('payment.'.$p->method) }} {{ $p->reference ? '· '.$p->reference : '' }} <span class="muted">· {{ $p->created_at->format('d/m H:i') }} · {{ $p->user?->name }}</span></span><b>{{ $p->type === 'refund' ? '−' : '' }}{{ money($p->amount) }}</b></div>@endforeach
      @if((float) $sale->change_given > 0)<p class="small muted">Monnaie rendue : {{ money($sale->change_given) }}</p>@endif
    </div>
    <div class="card"><h3>Journal</h3>@foreach($sale->auditLogs as $l)<p class="small">{{ d($l->created_at, true) }} – {{ $l->user?->name }} – <b>{{ $l->action }}</b> {{ $l->reason ? '– '.$l->reason : '' }}</p>@endforeach</div>
  </div>
  <div>
    <div class="card"><div class="card-head"><h3>Client</h3></div>
      @if($sale->customer)<p><a href="{{ route('admin.customers.show', $sale->customer) }}"><b>{{ $sale->customer->displayName() }}</b></a><br><span class="small muted">{{ $sale->customer->phone }} {{ $sale->customer->ice ? '· ICE '.$sale->customer->ice : '' }}</span></p>@else<p class="muted small">Client comptoir (non identifié).</p>@endif
      @can('retail.sell')
      <form method="post" action="{{ route('admin.retail.sales.invoice', $sale) }}" class="stack">@csrf
        @if(! $sale->customer && ! $inv)<div class="field"><label class="small">Facturer au nom de</label><select name="customer_id"><option value="">Client comptoir</option>@foreach(\App\Models\Customer::orderBy('name')->limit(500)->get() as $c)<option value="{{ $c->id }}">{{ $c->displayName() }} {{ $c->ice ? '· ICE '.$c->ice : '' }}</option>@endforeach</select><div class="help">Créez d'abord le client (Clients → Nouveau) s'il n'existe pas.</div></div>@endif
        <button class="btn btn-block">@icon('file') {{ $inv ? 'Ouvrir la facture' : 'Établir la facture A4' }}</button>
      </form>
      @endcan
    </div>
    @can('retail.refund')
    @if($sale->status !== 'refunded')
    <form class="card" method="post" action="{{ route('admin.retail.sales.refund', $sale) }}" data-once>@csrf
      <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
      <div class="card-head"><h3>@icon('undo') Retour / remboursement</h3></div>
      @foreach($sale->items as $i)@php($left = (float) $i->quantity - (float) $i->returned_qty)
        @if($left > 0)<div class="flex between small" style="margin-bottom:6px"><span>{{ $i->name }}</span><input type="number" name="qty[{{ $i->id }}]" min="0" max="{{ $left }}" step="{{ $i->product?->unit === 'piece' ? 1 : 0.001 }}" placeholder="0 / {{ $left }}" style="width:100px"></div>@endif
      @endforeach
      <div class="field"><label class="small">Rembourser par</label><select name="method">@foreach(config('hocreative.retail_payment_methods') as $m)<option value="{{ $m }}">{{ __('payment.'.$m) }}</option>@endforeach</select>
        @if(! $openSession)<div class="help">Aucune caisse ouverte : remboursement en espèces impossible.</div>@endif</div>
      <input type="hidden" name="restock" value="0"><label class="check small"><input type="checkbox" name="restock" value="1" checked> Remettre les articles en stock</label>
      <div class="field" style="margin-top:8px"><label class="small">Motif *</label><input name="reason" required placeholder="ex. article défectueux"></div>
      <button class="btn btn-danger btn-block">Enregistrer le retour</button>
    </form>
    @endif
    @if($inv)
    <form class="card" method="post" action="{{ route('admin.documents.credit', $inv) }}" data-once>@csrf
      <div class="card-head"><h3>@icon('file') Avoir sur facture {{ $inv->number }}</h3></div>
      <p class="help" style="margin-top:-6px">À émettre après un retour, pour annuler tout ou partie de la facture.</p>
      <div class="grid-2"><div class="field"><label class="small">Montant TTC</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ $sale->refunded_total > 0 ? $sale->refunded_total : $inv->total }}" required></div>
        <div class="field"><label class="small">TVA de l'article</label><select name="tax_rate">@foreach($sale->items->pluck('tax_rate')->unique() as $r)<option value="{{ (float) $r }}">{{ (float) $r }} %</option>@endforeach</select></div></div>
      <div class="field"><label class="small">Motif</label><input name="reason" required value="Retour marchandise – ticket {{ $sale->number }}"></div>
      <button class="btn btn-ghost btn-block">Émettre l'avoir</button>
      @foreach($sale->documents->where('type', 'credit_note') as $cn)<p class="small"><a href="{{ route('files.document', $cn) }}" target="_blank">Avoir {{ $cn->number }}</a> – {{ money($cn->total) }}</p>@endforeach
    </form>
    @endif
    @endcan
  </div>
</div>
@endsection
