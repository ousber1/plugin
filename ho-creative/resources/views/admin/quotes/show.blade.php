@extends('layouts.admin')
@section('title', $quote->number)
@section('content')
<div class="page-head"><h1>Devis {{ $quote->number }} <span class="pill">{{ __('quote_status.'.$quote->status) }}</span></h1>
  <div class="flex">
    @if(! in_array($quote->status, ['accepted', 'converted']))<a class="btn btn-sm btn-ghost" href="{{ route('admin.quotes.revise', $quote) }}">✎ Réviser</a>@endif
    @if(in_array($quote->status, ['draft', 'sent']))<form method="post" action="{{ route('admin.quotes.send', $quote) }}">@csrf<button class="btn btn-sm">✉ {{ $quote->status === 'sent' ? 'Renvoyer' : 'Envoyer au client' }}</button></form>@endif
    @if($quote->status !== 'draft')<a class="btn btn-sm btn-ghost" href="{{ route('quote.public.pdf', $quote->token) }}" target="_blank">PDF</a>@endif
  </div></div>
<div class="split">
  <div>
    <div class="card table-wrap">
      <h3>Révision {{ $rev->revision }} @if($rev->sent_at)<span class="small muted">envoyée le {{ d($rev->sent_at, true) }}</span>@endif</h3>
      <table><thead><tr><th>Désignation</th><th class="num">Qté</th><th class="num">P.U. HT</th><th class="num">Total HT</th></tr></thead><tbody>
        @foreach($rev->items->sortBy('sort') as $i)<tr><td>{{ $i->description }} @if($i->rule_version)<span class="small muted">· grille v{{ $i->rule_version }}</span>@endif</td><td class="num">{{ $i->quantity }}</td><td class="num">{{ money(round((float) $i->unit_price, 2)) }}</td><td class="num">{{ money($i->total) }}</td></tr>@endforeach
      </tbody></table>
      <div style="max-width:380px;margin-inline-start:auto">@include('store._totals', ['t' => ['subtotal' => $rev->subtotal, 'discount_total' => $rev->discount_total, 'shipping_total' => $rev->shipping_total, 'tax_rate' => $rev->tax_rate, 'tax_total' => $rev->tax_total, 'total' => $rev->total]])</div>
      @if($rev->discount_reason)<p class="small">Remise : {{ $rev->discount_reason }}</p>@endif
    </div>
    @if($quote->revisions->count() > 1)
      <div class="card"><h3>Historique des révisions</h3>@foreach($quote->revisions->sortByDesc('revision') as $r)<p class="small">Rév. {{ $r->revision }} – {{ money($r->total) }} – {{ d($r->created_at, true) }}</p>@endforeach</div>
    @endif
    <div class="card"><h3>Journal</h3>@foreach($quote->auditLogs as $l)<p class="small">{{ d($l->created_at, true) }} – {{ $l->user?->name ?? 'Client' }} – {{ $l->action }} {{ $l->reason ? '– '.$l->reason : '' }}</p>@endforeach</div>
  </div>
  <div>
    <div class="card"><dl class="dl"><dt>Client</dt><dd><a href="{{ route('admin.customers.show', $quote->customer) }}">{{ $quote->customer->displayName() }}</a></dd><dt>Canal</dt><dd>{{ __('channel.'.$quote->channel) }}</dd><dt>Expire</dt><dd>{{ d($quote->expires_at) }}</dd>
      <dt>Lien client</dt><dd class="small" style="word-break:break-all">{{ route('quote.public', $quote->token) }}</dd>
      @if($quote->responded_at)<dt>Réponse</dt><dd>{{ d($quote->responded_at, true) }} – {{ $quote->response_name }} {{ $quote->response_note ? '« '.$quote->response_note.' »' : '' }}</dd>@endif</dl>
      @if($quote->customer->whatsapp || $quote->customer->phone)<a class="btn btn-sm btn-wa" target="_blank" href="https://wa.me/{{ preg_replace('/\D/', '', $quote->customer->whatsapp ?: $quote->customer->phone) }}?text={{ urlencode('Bonjour, voici votre devis '.$quote->number.' : '.route('quote.public', $quote->token)) }}">Partager sur WhatsApp</a>@endif
    </div>
    @if(in_array($quote->status, ['draft', 'sent']))
      <form class="card" method="post" action="{{ route('admin.quotes.respond', $quote) }}">@csrf
        <h3>Enregistrer la réponse du client</h3><p class="small muted">Accord reçu par WhatsApp, téléphone ou au comptoir.</p>
        <input name="name" placeholder="Nom de la personne" required value="{{ $quote->customer->name }}">
        <input name="note" placeholder="Preuve / canal (ex. WhatsApp 12/05 14h32)" required style="margin-top:6px">
        <div class="flex" style="margin-top:8px"><button class="btn btn-sm btn-accent" name="decision" value="accept">Accepté</button><button class="btn btn-sm btn-ghost" name="decision" value="reject">Refusé</button></div>
      </form>
    @endif
    @if($quote->status === 'accepted')
      <form class="card" method="post" action="{{ route('admin.quotes.convert', $quote) }}" data-once>@csrf
        <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <h3>Convertir en commande</h3><p class="small muted">Crée une seule commande avec les montants de la révision acceptée ({{ $quote->accepted_revision }}).</p>
        @can('payments.record')
          <div class="grid-2"><div class="field"><label class="small">Acompte reçu (DH)</label><input type="number" step="0.01" min="0" name="deposit" placeholder="{{ number_format((float) $rev->total * (float) setting('checkout.deposit_percent', 50) / 100, 2, '.', '') }}"></div>
          <div class="field"><label class="small">Moyen</label><select name="deposit_method">@foreach(config('hocreative.payment_methods') as $m)<option value="{{ $m }}">{{ __('payment.'.$m) }}</option>@endforeach</select></div></div>
        @endcan
        <button class="btn btn-accent">Créer la commande</button>
      </form>
    @endif
    @if($quote->order)<div class="alert alert-ok">Commande : <a href="{{ route('admin.orders.show', $quote->order) }}">{{ $quote->order->number }}</a></div>@endif
  </div>
</div>
@endsection
