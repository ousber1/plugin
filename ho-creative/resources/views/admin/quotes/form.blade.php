@extends('layouts.admin')
@section('title', 'Devis')
@section('content')
@php
  $existing = $rev ? $rev->items->sortBy('sort')->map(fn ($i) => $i->product_id ? ['product_id' => $i->product_id, 'selection' => $i->selection] : ['description' => $i->description, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price])->values()->all()
    : ($prefill ? [['description' => $prefill->product_type.($prefill->width ? ' – '.(float) $prefill->width.' × '.(float) $prefill->height.' '.$prefill->unit : '').($prefill->material ? ' – '.$prefill->material : '').($prefill->finishes ? ' – '.$prefill->finishes : ''), 'quantity' => $prefill->quantity, 'unit_price' => '']] : [[]]);
@endphp
<h1>{{ $quote ? 'Réviser le devis '.$quote->number.' (révision '.($quote->current_revision + 1).')' : 'Nouveau devis' }}</h1>
<form method="post" action="{{ $quote ? route('admin.quotes.revision', $quote) : route('admin.quotes.store') }}" data-once>@csrf
  <input type="hidden" name="quote_request_id" value="{{ $prefill?->id }}">
  <div class="card"><div class="grid-4">
    <div class="field"><label>Client *</label>
      @if($quote)<input type="hidden" name="customer_id" value="{{ $quote->customer_id }}"><input value="{{ $quote->customer->displayName() }}" disabled>
      @else<select name="customer_id" required><option value="">—</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($customer?->id === $c->id)>{{ $c->displayName() }} {{ $c->phone }}</option>@endforeach</select>
        <a class="small" href="{{ route('admin.customers.create', ['return' => url()->full()]) }}">+ nouveau client</a>@if($prefill && ! $customer)<div class="help">Demande de {{ $prefill->name }} ({{ $prefill->email }}, {{ $prefill->phone }}) – créez le client si besoin.</div>@endif
      @endif</div>
    <div class="field"><label>Canal</label><select name="channel">@foreach(config('hocreative.order_channels') as $ch)<option value="{{ $ch }}" @selected(($quote?->channel ?? ($prefill ? 'web' : 'whatsapp')) === $ch)>{{ __('channel.'.$ch) }}</option>@endforeach</select></div>
    <div class="field"><label>Valable jusqu'au</label><input type="date" name="expires_at" value="{{ $quote?->expires_at?->toDateString() ?? now()->addDays((int) setting('quotes.validity_days', 30))->toDateString() }}"></div>
    <div class="field"><label>Langue du document</label><select name="locale"><option value="fr">Français</option><option value="ar" @selected($quote?->locale === 'ar')>العربية</option></select></div>
  </div></div>
  @include('admin._lines', ['existing' => $existing])
  <div class="card"><div class="grid-4">
    <div class="field"><label>Livraison</label><select name="shipping_method"><option value="pickup">Retrait atelier</option><option value="delivery" @selected($rev?->shipping_method === 'delivery')>Livraison</option></select></div>
    <div class="field"><label>Zone</label><select name="shipping_zone_id"><option value="">—</option>@foreach($zones as $z)<option value="{{ $z->id }}" @selected($rev?->shipping_zone_id === $z->id)>{{ $z->t('name') }} ({{ money($z->price) }})</option>@endforeach</select></div>
    @can('orders.discount')
    <div class="field"><label>Remise HT (DH)</label><input type="number" step="0.01" min="0" name="manual_discount" value="{{ $rev && (float) $rev->discount_total > 0 ? $rev->discount_total : '' }}"></div>
    <div class="field"><label>Motif de la remise</label><input name="discount_reason" value="{{ $rev?->discount_reason }}"></div>
    @endcan
  </div>
  <div class="field"><label>Notes / conditions (visibles sur le devis)</label><textarea name="notes">{{ $rev?->notes ?? ($prefill?->notes) }}</textarea></div>
  <button class="btn btn-accent">{{ $quote ? 'Créer la révision' : 'Créer le devis' }}</button>
  <p class="small muted">Les lignes catalogue sont tarifées par le moteur de prix au moment de l'enregistrement ; le montant est figé dans la révision.</p></div>
</form>
@endsection
