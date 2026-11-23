@extends('layouts.admin')
@section('title', $order->number)
@section('content')
@php($u = auth()->user())
@php($money = $u->can('finance.view') || $u->can('orders.manage'))
<div class="page-head">
  <h1>{{ $order->number }} @if($order->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</h1>
  <div class="flex">
    <span class="pill">{{ __('order_status.'.$order->status) }}</span><span class="pill">{{ __('payment_status.'.$order->payment_status) }}</span><span class="pill">{{ __('fulfillment.'.$order->fulfillment_status) }}</span>
    @can('orders.manage')
      @if($order->status === 'pending')<form method="post" action="{{ route('admin.orders.confirm', $order) }}">@csrf<button class="btn btn-sm">✔ Confirmer</button></form>@endif
    @endcan
    @can('documents.issue')
      @if($order->status !== 'cancelled')
        <form method="post" action="{{ route('admin.orders.document', $order) }}">@csrf<input type="hidden" name="type" value="proforma"><button class="btn btn-sm btn-ghost">Pro forma</button></form>
        <form method="post" action="{{ route('admin.orders.document', $order) }}" data-confirm="Émettre la facture ? Elle ne pourra plus être modifiée (corrections par avoir).">@csrf<input type="hidden" name="type" value="invoice"><button class="btn btn-sm btn-ghost">Facture</button></form>
      @endif
    @endcan
  </div>
</div>
<p class="muted small">{{ __('channel.'.$order->channel) }} · passée le {{ d($order->placed_at, true) }} @if($order->creator)par {{ $order->creator->name }}@endif @if($order->quote)· devis <a href="{{ route('admin.quotes.show', $order->quote) }}">{{ $order->quote->number }}</a> (rév. {{ $order->quote_revision }})@endif
 · prête le {{ d($order->estimated_ready_at) }} · livraison estimée {{ d($order->estimated_delivery_at) }} @if($order->estimate_depends_on_proof)(dépend du BAT)@endif</p>

<div class="split">
<div>
@foreach($order->items as $item)
  @php($job = $item->job)
  <div class="card">
    <div class="flex between" style="align-items:flex-start">
      <div><h3 style="margin:0">{{ $item->description }}</h3>
        <div class="small muted">{{ $item->quantity }} ex. @if($item->width_mm)· {{ (float) $item->width_mm }} × {{ (float) $item->height_mm }} mm @endif @if($item->rule_version)· grille v{{ $item->rule_version }}@endif · fichier : {{ __('artwork_mode.'.$item->artwork_mode) }} @if($item->remake_of_id)· <span class="pill pill-warn">Reprise : {{ $item->remake_reason }}</span>@endif</div>
        @if($item->notes)<p class="small">📝 {{ $item->notes }}</p>@endif
      </div>
      @if($money)<div class="right"><strong>{{ money($item->total) }}</strong>@if((float) $item->manual_discount > 0)<div class="small muted">remise {{ money($item->manual_discount) }} – {{ $item->manual_discount_reason }}</div>@endif</div>@endif
    </div>
    @if($job)<p class="small">🏭 <a href="{{ route('admin.production.show', $job) }}">{{ $job->number }}</a> – <span class="pill">{{ __('stage.'.$job->stage) }}</span> @if($b = $production->blockers($job, 'printing'))<span class="error-text">⛔ {{ implode(' ', $b) }}</span>@endif</p>@endif
    @if($money && $item->breakdown && ! empty($item->breakdown['components']))
      <details class="small"><summary>Détail du prix (figé à la commande)</summary><table>@foreach($item->breakdown['components'] as $c)<tr><td>{{ $c['label'] }}</td><td class="num">{{ money($c['amount']) }}</td></tr>@endforeach</table></details>
    @endif

    <h4 style="margin-top:10px">Fichiers</h4>
    @if($item->design && $item->design->exports)<p class="small">🎨 Design en ligne : <a href="{{ route('files.design', [$item->design, 'pdf']) }}" target="_blank">PDF</a> · <a href="{{ route('files.design', [$item->design, 'front']) }}" target="_blank">PNG recto</a> @if(! empty($item->design->exports['back']))· <a href="{{ route('files.design', [$item->design, 'back']) }}" target="_blank">PNG verso</a>@endif <span class="muted">(raster RVB – contrôle prépresse requis)</span></p>@endif
    @forelse($item->artworkFiles->sortByDesc('id') as $f)
      <div class="small" style="border-top:1px dashed var(--line);padding-top:6px;margin-top:6px">
        📄 <strong>{{ $f->side }} v{{ $f->version }}</strong> <a href="{{ route('files.artwork', $f) }}" target="_blank">{{ $f->original_name }}</a> · {{ number_format($f->size / 1048576, 1) }} Mo · {{ d($f->created_at, true) }} {{ $f->uploader?->name ? '· '.$f->uploader->name : '' }}
        <span class="pill {{ $f->review_status === 'ok' ? 'pill-ok' : ($f->review_status === 'issues' ? 'pill-err' : '') }}">{{ $f->review_status }}</span>
        @include('partials.artwork-checks', ['file' => $f])
        @can('artwork.manage')
          <form method="post" action="{{ route('admin.artwork.review', $f) }}" class="flex">@csrf<input name="review_note" placeholder="Remarque prépresse" style="flex:1"><button class="btn btn-sm btn-ghost" name="review_status" value="ok">✔ Conforme</button><button class="btn btn-sm btn-ghost" name="review_status" value="issues">⚠ À corriger</button></form>
        @endcan
      </div>
    @empty
      <p class="small muted">Aucun fichier.</p>
    @endforelse
    @can('artwork.manage')
      <form method="post" action="{{ route('admin.items.artwork', $item) }}" enctype="multipart/form-data" class="flex" style="margin-top:6px">@csrf<select name="side" style="width:auto"><option value="front">Recto</option><option value="back">Verso</option></select><input type="file" name="file" required style="flex:1"><button class="btn btn-sm btn-ghost">Ajouter un fichier</button></form>
    @endcan

    <h4 style="margin-top:10px">BAT @if(! $item->requires_proof)<span class="small muted">(non requis)</span>@endif</h4>
    @foreach($item->proofs->sortByDesc('version') as $p)
      <div class="small">v{{ $p->version }} – <a href="{{ route('files.proof', $p) }}" target="_blank">{{ $p->original_name }}</a> – <span class="pill {{ $p->status === 'approved' ? 'pill-ok' : ($p->status === 'pending' ? 'pill-warn' : '') }}">{{ __('proof_status.'.$p->status) }}</span>
        {{ d($p->created_at, true) }} @if($p->responded_at)· réponse {{ d($p->responded_at, true) }} {{ $p->responder?->name ?? 'client (lien)' }} @if($p->customer_comment)« {{ $p->customer_comment }} »@endif @endif</div>
      @if($p->status === 'pending' && $loop->first)
        @can('proofs.manage')
          <form method="post" action="{{ route('admin.proofs.record', $p) }}" class="flex" style="margin:4px 0">@csrf<input name="comment" required placeholder="Preuve (ex. approuvé au comptoir, WhatsApp 14h10)" style="flex:1"><button class="btn btn-sm btn-ghost" name="decision" value="approved">Approuvé par le client</button><button class="btn btn-sm btn-ghost" name="decision" value="changes_requested">Corrections</button></form>
        @endcan
      @endif
    @endforeach
    @can('proofs.manage')
      <form method="post" action="{{ route('admin.items.proof', $item) }}" enctype="multipart/form-data" class="stack" style="margin-top:6px">@csrf
        <div class="flex"><input type="file" name="file" required accept=".pdf,.png,.jpg,.jpeg" style="flex:1"><input name="message" placeholder="Message au client" style="flex:1"><button class="btn btn-sm">Envoyer un BAT v{{ $item->proofs->max('version') + 1 }}</button></div>
        @if($item->proofs->isNotEmpty())<p class="help">Un nouveau BAT remplace le précédent et exige une nouvelle approbation.</p>@endif
      </form>
    @endcan
    @can('production.manage')
      <details class="small" style="margin-top:8px"><summary>Réimpression / reprise</summary>
        <form method="post" action="{{ route('admin.items.remake', $item) }}" class="flex">@csrf<input name="reason" required placeholder="Motif (défaut couleur, coupe…)" style="flex:1"><label class="check"><input type="checkbox" name="charge" value="1"> facturer</label><button class="btn btn-sm btn-ghost">Créer la reprise</button></form></details>
    @endcan
  </div>
@endforeach

<div class="card"><h3>Journal</h3>
  @foreach($order->auditLogs as $l)<p class="small">{{ d($l->created_at, true) }} – {{ $l->user?->name ?? 'Système / client' }} – <strong>{{ $l->action }}</strong> {{ $l->reason ? '– '.$l->reason : '' }}</p>@endforeach
</div>
</div>

<div>
  <div class="card"><h3>Client</h3>
    <p><a href="{{ route('admin.customers.show', $order->customer) }}">{{ $order->customer->displayName() }}</a><br><span class="small">{{ $order->contact_name }} · {{ $order->contact_phone }} · {{ $order->contact_email }}</span></p>
    @if($order->contact_phone)<a class="btn btn-sm btn-wa" target="_blank" href="https://wa.me/{{ preg_replace('/\D/', '', $order->customer->whatsapp ?: $order->contact_phone) }}?text={{ urlencode('Bonjour, concernant votre commande '.$order->number) }}">WhatsApp</a>@endif
    @if($order->notes)<p class="small">💬 {{ $order->notes }}</p>@endif
    <p class="small">{{ $order->shipping_method === 'pickup' ? '🏪 Retrait atelier' : '🚚 '.($order->shippingZone?->t('name')).' – '.collect($order->shipping_address ?? [])->only(['line1', 'line2', 'city'])->filter()->implode(', ') }}</p>
    @if(! $order->user_id && $order->guest_token)<p class="small muted">Lien de suivi invité : {{ route('guest.order', $order->guest_token) }}</p>@endif
  </div>

  @if($money)
  <div class="card"><h3>Montants</h3>
    @include('store._totals', ['t' => ['subtotal' => $order->subtotal, 'discount_total' => $order->discount_total, 'shipping_total' => $order->shipping_total, 'tax_rate' => $order->tax_rate, 'tax_total' => $order->tax_total, 'total' => $order->total]])
    @if($order->discount_reason)<p class="small">Remise : {{ $order->discount_reason }}</p>@endif
    <p class="small">Encaissé : <strong>{{ money($order->amount_paid) }}</strong> · Remboursé : {{ money($order->amount_refunded) }} · <strong>Solde : {{ money($order->balance()) }}</strong> @if((float) $order->deposit_required > 0)· acompte demandé {{ money($order->deposit_required) }}@endif</p>
    <table class="small">@foreach($order->payments as $p)<tr><td>{{ d($p->paid_at, true) }}</td><td>{{ $p->type === 'refund' ? '↩ Remb.' : '✔' }} {{ __('payment.'.$p->method) }} {{ $p->reference }}</td><td class="num">{{ money($p->amount) }}</td>
      <td>@can('documents.issue')<form method="post" action="{{ route('admin.orders.document', $order) }}">@csrf<input type="hidden" name="type" value="receipt"><input type="hidden" name="payment_id" value="{{ $p->id }}"><button class="btn btn-sm btn-ghost">Reçu</button></form>@endcan</td></tr>@endforeach</table>
    @canany(['payments.record', 'payments.refund'])
      <form method="post" action="{{ route('admin.orders.payment', $order) }}" class="stack" style="margin-top:8px" data-once>@csrf
        <input type="hidden" name="key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="grid-2">
          <select name="type">@can('payments.record')<option value="payment">Encaissement</option>@endcan @can('payments.refund')<option value="refund">Remboursement</option>@endcan</select>
          <select name="method">@foreach(config('hocreative.payment_methods') as $m)<option value="{{ $m }}">{{ __('payment.'.$m) }}</option>@endforeach</select>
          <input type="number" step="0.01" min="0.01" name="amount" required placeholder="Montant" value="{{ (float) $order->balance() > 0 ? $order->balance() : '' }}">
          <input name="reference" placeholder="Référence (virement, chèque…)">
        </div>
        <input name="notes" placeholder="Note (obligatoire pour un remboursement)"><button class="btn btn-sm">Enregistrer</button>
      </form>
    @endcanany
  </div>
  @endif

  <div class="card"><h3>Documents</h3>
    @forelse($order->documents->sortByDesc('id') as $doc)
      <p class="small">📄 <a href="{{ route('files.document', $doc) }}" target="_blank">{{ __('doc.'.$doc->type) }} {{ $doc->number }}</a> – {{ d($doc->issued_at) }} @if($money)– {{ money($doc->total) }}@endif
        @if($doc->type === 'invoice')@can('payments.refund')<details><summary>Avoir</summary><form method="post" action="{{ route('admin.documents.credit', $doc) }}" class="flex">@csrf<input type="number" step="0.01" name="amount" value="{{ $doc->total }}" style="width:120px"><input name="reason" required placeholder="Motif" style="flex:1"><button class="btn btn-sm btn-ghost">Émettre l'avoir</button></form></details>@endcan @endif</p>
    @empty<p class="small muted">Aucun document émis.</p>@endforelse
  </div>

  <div class="card"><h3>Livraison / retrait</h3>
    @foreach($order->shipments as $s)
      <form method="post" action="{{ route('admin.shipments.update', $s) }}" class="stack small" style="border-bottom:1px dashed var(--line);padding-bottom:8px">@csrf @method('put')
        <strong>{{ $s->method }}</strong> {{ $s->carrier }} · expédié {{ d($s->dispatched_at, true) }} · livré {{ d($s->delivered_at, true) }}
        <div class="grid-2"><select name="status">@foreach(['pending', 'ready', 'shipped', 'delivered', 'picked_up', 'returned'] as $st)<option value="{{ $st }}" @selected($s->status === $st)>{{ __('shipment_status.'.$st) }}</option>@endforeach</select>
          <input name="tracking_number" value="{{ $s->tracking_number }}" placeholder="N° de suivi"><input name="carrier" value="{{ $s->carrier }}" placeholder="Transporteur"><input type="datetime-local" name="dispatched_at" value="{{ $s->dispatched_at?->format('Y-m-d\TH:i') }}"></div>
        @can('delivery.manage')<div class="flex"><button class="btn btn-sm btn-ghost">Mettre à jour</button></div>@endcan
      </form>
      @can('documents.issue')<form method="post" action="{{ route('admin.orders.document', $order) }}">@csrf<input type="hidden" name="type" value="delivery_note"><input type="hidden" name="shipment_id" value="{{ $s->id }}"><button class="btn btn-sm btn-ghost">Bon de livraison</button></form>@endcan
    @endforeach
    @can('delivery.manage')
      <details style="margin-top:8px" @if($order->shipments->isEmpty()) open @endif><summary class="small">+ Nouvelle expédition / retrait</summary>
        <form method="post" action="{{ route('admin.orders.shipment', $order) }}" class="stack">@csrf
          <div class="grid-2"><select name="method"><option value="pickup" @selected($order->shipping_method === 'pickup')>Retrait atelier</option><option value="local">Livraison locale</option><option value="carrier" @selected($order->shipping_method === 'delivery')>Transporteur</option></select>
            <select name="status"><option value="ready">Prête</option><option value="shipped">Expédiée</option><option value="picked_up">Retirée</option><option value="delivered">Livrée</option></select>
            <input name="carrier" placeholder="Transporteur" value="{{ $order->shippingZone?->carrier }}"><input name="tracking_number" placeholder="N° de suivi"></div>
          <input name="recipient_name" placeholder="Remis à (nom)"><button class="btn btn-sm">Enregistrer</button></form></details>
    @endcan
  </div>

  @can('orders.manage')
  <div class="card"><h3>Notes internes</h3>
    <form method="post" action="{{ route('admin.orders.notes', $order) }}">@csrf<textarea name="internal_notes">{{ $order->internal_notes }}</textarea><button class="btn btn-sm" style="margin-top:6px">Enregistrer</button></form>
    @if($order->status !== 'cancelled')
      <details style="margin-top:10px"><summary class="small error-text">Annuler la commande</summary>
        <form method="post" action="{{ route('admin.orders.cancel', $order) }}" data-confirm="Annuler la commande ?">@csrf<input name="reason" required placeholder="Motif"><button class="btn btn-sm btn-danger" style="margin-top:6px">Annuler</button></form></details>
    @endif
  </div>
  @endcan
</div>
</div>
@endsection
