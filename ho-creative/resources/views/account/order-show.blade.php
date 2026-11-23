@extends($guest ? 'layouts.store' : 'account._layout')
@section('title', __('Commande :n', ['n' => $order->number]))
@push('head')<meta name="robots" content="noindex,nofollow">@endpush
@php
  $steps = ['received', 'awaiting_artwork', 'awaiting_approval', 'in_production', 'ready', 'shipped', 'delivered'];
  $cur = $order->publicStatus();
  $curIdx = array_search($cur, $steps);
  $uploadRoute = fn ($item) => $guest ? route('guest.upload', [$order->guest_token, $item->id]) : route('account.orders.upload', [$order, $item->id]);
  $proofRoute = fn ($p) => $guest ? route('guest.proof', [$order->guest_token, $p->id]) : route('account.proof.respond', $p);
@endphp
@section($guest ? 'content' : 'account')
@if($guest)<div class="container section" style="padding-top:20px">@include('partials.flash')@endif
<div class="flex between">
  <h1 style="margin:0">{{ __('Commande :n', ['n' => $order->number]) }}</h1>
  <div class="flex">
    @if(! $guest && $order->status !== 'cancelled')
      <form method="post" action="{{ route('account.orders.reorder', $order) }}">@csrf<button class="btn btn-ghost btn-sm">↻ {{ __('Commander à nouveau') }}</button></form>
    @endif
    @if(setting('payments.online') && in_array($order->payment_status, ['unpaid', 'partial']) && $order->status !== 'cancelled' && (float) $order->balance() > 0)
      <a class="btn btn-accent btn-sm" href="{{ route('payment.start', $order) }}">💳 {{ __('Payer en ligne') }} ({{ money($order->balance()) }})</a>
    @endif
  </div>
</div>
<p class="muted">{{ d($order->placed_at, true) }} · {{ __('Paiement') }} : <span class="pill">{{ __('payment_status.'.$order->payment_status) }}</span> · {{ __('Reste à payer') }} : <strong>{{ money($order->balance()) }}</strong></p>

@if($cur === 'cancelled')
  <div class="alert alert-err">{{ __('Cette commande a été annulée.') }}</div>
@else
  <ol class="timeline" aria-label="{{ __('Avancement') }}">
    @foreach($steps as $i => $s)
      <li @class(['done' => $curIdx !== false && $i < $curIdx, 'current' => $s === $cur]) @if($s === $cur) aria-current="step" @endif>{{ __('public_status.'.$s) }}</li>
    @endforeach
  </ol>
  <p class="small" style="margin-top:8px">📅 {{ $order->shipping_method === 'delivery' ? __('Livraison estimée') : __('Disponible à l\'atelier') }} : <strong>{{ d($order->estimated_delivery_at) }}</strong>
    @if($order->estimate_depends_on_proof)<span class="muted">– {{ __('sous réserve de la validation de votre BAT ; la date est recalculée à partir de votre approbation.') }}</span>@endif</p>
@endif

@foreach($order->items as $item)
  <div class="card">
    <div class="flex between" style="align-items:flex-start">
      <div>
        <h3 style="margin:0">{{ $item->product?->t('name') ?: $item->description }}</h3>
        <p class="small muted" style="margin:.3em 0">{{ collect([$item->options_snapshot['size'] ?? null])->merge(collect($item->options_snapshot['options'] ?? [])->pluck('value_label'))->filter()->implode(' · ') }} · {{ $item->quantity }} {{ __('ex.') }}</p>
        @if($item->remake_of_id)<span class="pill pill-warn">{{ __('Réimpression') }}</span>@endif
      </div>
      <strong>{{ money($item->total) }} <small class="muted">{{ __('HT') }}</small></strong>
    </div>

    <h4 style="margin-top:12px">{{ __('Fichiers d\'impression') }}</h4>
    @if($item->design && $item->design->exports)
      <p class="small">🎨 {{ __('Design créé en ligne') }} – <a href="{{ route('files.design', [$item->design, 'pdf']) }}" target="_blank">PDF</a></p>
    @endif
    @forelse($item->artworkFiles->sortByDesc('version') as $f)
      <div class="small">📄 <strong>{{ $f->side === 'back' ? __('Verso') : ($f->side === 'reference' ? __('Référence') : __('Recto')) }} v{{ $f->version }}</strong> – <a href="{{ route('files.artwork', $f) }}" target="_blank">{{ $f->original_name }}</a> · {{ d($f->created_at, true) }}
        @if($f->review_status === 'issues')<span class="pill pill-err">{{ __('À corriger') }}</span> {{ $f->review_note }}@elseif($f->review_status === 'ok')<span class="pill pill-ok">{{ __('Validé par le prépresse') }}</span>@endif
      </div>
      @if($loop->first)@include('partials.artwork-checks', ['file' => $f])@endif
    @empty
      @if(! $item->design)<p class="small muted">{{ $item->artwork_mode === 'design_service' ? __('Notre studio prépare votre design. Un BAT vous sera soumis.') : __('Aucun fichier reçu pour le moment.') }}</p>@endif
    @endforelse
    @if(! in_array($order->status, ['cancelled', 'completed']) && $item->proof_status !== 'approved' && $item->artwork_mode !== 'design_service')
      <form method="post" action="{{ $uploadRoute($item) }}" enctype="multipart/form-data" data-upload class="dropzone" style="margin-top:8px">@csrf
        <div class="flex">
          <select name="side" aria-label="{{ __('Face') }}" style="width:auto"><option value="front">{{ __('Recto') }}</option>@if($item->needsBackSide())<option value="back">{{ __('Verso') }}</option>@endif</select>
          <input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg" aria-label="{{ __('Fichier') }}" style="flex:1">
          <button class="btn btn-sm">⬆ {{ __('Envoyer') }}</button>
        </div>
        <div class="progress"><i></i></div><p class="upload-msg small"></p>
      </form>
    @endif

    @if($item->proofs->isNotEmpty())
      @php($p = $item->proofs->sortByDesc('version')->first())
      <h4 style="margin-top:14px">{{ __('Bon à tirer (BAT)') }}</h4>
      <div class="alert {{ $p->status === 'approved' ? 'alert-ok' : ($p->status === 'pending' ? 'alert-warn' : 'alert-info') }}">
        <strong>BAT v{{ $p->version }}</strong> – <a href="{{ route('files.proof', $p) }}" target="_blank">{{ __('Voir le BAT') }}</a> · {{ __('proof_status.'.$p->status) }}
        @if($p->staff_message)<p class="small" style="margin:.4em 0">{{ $p->staff_message }}</p>@endif
        @if($p->status === 'pending')
          <form method="post" action="{{ $proofRoute($p) }}" class="stack" style="margin-top:10px" data-once>@csrf
            <label class="check"><input type="checkbox" name="confirm" value="1"> {{ __('J\'ai vérifié textes, couleurs, dimensions et orthographe. J\'approuve le BAT v:v pour impression.', ['v' => $p->version]) }}</label>
            <label for="c{{ $p->id }}" class="small">{{ __('Corrections demandées (si refus)') }}</label>
            <textarea id="c{{ $p->id }}" name="comment" maxlength="2000"></textarea>
            <div class="flex"><button class="btn btn-accent btn-sm" name="decision" value="approved">✔ {{ __('Approuver') }}</button><button class="btn btn-ghost btn-sm" name="decision" value="changes_requested">✎ {{ __('Demander des corrections') }}</button></div>
          </form>
        @elseif($p->responded_at)
          <p class="small" style="margin:.4em 0">{{ __('Réponse du :d', ['d' => d($p->responded_at, true)]) }} @if($p->customer_comment)– « {{ $p->customer_comment }} »@endif</p>
        @endif
      </div>
      @if($item->proofs->count() > 1)
        <details class="small"><summary>{{ __('Historique des BAT') }}</summary>
          <ul>@foreach($item->proofs->sortByDesc('version') as $op)<li>v{{ $op->version }} – {{ __('proof_status.'.$op->status) }} – {{ d($op->created_at, true) }}</li>@endforeach</ul>
        </details>
      @endif
    @endif
  </div>
@endforeach

<div class="split" style="margin-top:16px">
  <div class="card">
    <h3>{{ __('Livraison') }}</h3>
    @if($order->shipping_method === 'pickup')<p>🏪 {{ __('Retrait à l\'atelier') }}</p>@else
      <p>{{ $order->shipping_address['name'] ?? '' }}<br>{{ $order->shipping_address['line1'] ?? '' }} {{ $order->shipping_address['line2'] ?? '' }}<br>{{ $order->shipping_address['city'] ?? '' }}</p>@endif
    @foreach($order->shipments as $s)
      <p class="small">🚚 {{ __('shipment_status.'.$s->status) }} @if($s->carrier)· {{ $s->carrier }}@endif @if($s->tracking_number)· {{ __('Suivi') }} : <strong>{{ $s->tracking_number }}</strong>@endif @if($s->dispatched_at)· {{ d($s->dispatched_at) }}@endif</p>
    @endforeach
    <h3 style="margin-top:12px">{{ __('Documents') }}</h3>
    @forelse($order->documents->whereIn('type', ['invoice', 'credit_note', 'receipt', 'proforma', 'delivery_note']) as $doc)
      <p class="small">📄 <a href="{{ route('files.document', $doc) }}" target="_blank">{{ __('doc.'.$doc->type) }} {{ $doc->number }}</a> – {{ money($doc->total) }}</p>
    @empty
      <p class="small muted">{{ __('Les factures apparaîtront ici.') }}</p>
    @endforelse
  </div>
  <div class="card">
    <h3>{{ __('Montants') }}</h3>
    @include('store._totals', ['t' => ['subtotal' => $order->subtotal, 'discount_total' => $order->discount_total, 'shipping_total' => $order->shipping_total, 'tax_rate' => $order->tax_rate, 'tax_total' => $order->tax_total, 'total' => $order->total]])
    <p class="small">{{ __('Déjà payé') }} : {{ money($order->netPaid()) }} · {{ __('Reste à payer') }} : <strong>{{ money($order->balance()) }}</strong></p>
    @if($order->payment_method === 'bank_transfer' && (float) $order->balance() > 0)
      <p class="small muted">{{ __('Virement') }} : {{ setting('company.bank_name') }} – RIB {{ setting('company.rib') ?: '—' }} – {{ __('réf.') }} {{ $order->number }}</p>
    @endif
  </div>
</div>
@if($guest)</div>@endif
@endsection
