@extends('layouts.admin')
@section('title', 'Nouvelle commande')
@section('content')
<h1>Nouvelle commande (comptoir, téléphone, WhatsApp, B2B)</h1>
<form method="post" action="{{ route('admin.orders.store') }}" data-once>@csrf
  <input type="hidden" name="checkout_key" value="{{ \Illuminate\Support\Str::uuid() }}">
  <div class="card"><div class="grid-3">
    <div class="field"><label>Client *</label><select name="customer_id" required><option value="">—</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($customer?->id === $c->id)>{{ $c->displayName() }} {{ $c->phone }}</option>@endforeach</select>
      <a class="small" href="{{ route('admin.customers.create', ['return' => url()->full()]) }}">+ nouveau client</a></div>
    <div class="field"><label>Canal</label><select name="channel">@foreach(['walk_in', 'phone', 'whatsapp', 'b2b', 'email'] as $ch)<option value="{{ $ch }}">{{ __('channel.'.$ch) }}</option>@endforeach</select></div>
    <div class="field"><label>Livraison</label><select name="shipping_method"><option value="pickup">Retrait atelier</option><option value="delivery">Livraison</option></select></div>
    <div class="field"><label>Zone</label><select name="shipping_zone_id"><option value="">—</option>@foreach($zones as $z)<option value="{{ $z->id }}">{{ $z->t('name') }} ({{ money($z->price) }})</option>@endforeach</select></div>
    <div class="field"><label>Adresse</label><input name="line1"></div><div class="field"><label>Ville</label><input name="city"></div>
  </div></div>
  <div data-rows>
    <div data-rows-list>@include('admin.orders._line', ['i' => 0])</div>
    <template>@include('admin.orders._line', ['i' => '__i__'])</template>
    <button type="button" class="btn btn-sm btn-ghost" data-rows-add>+ Ajouter une ligne</button>
  </div>
  <div class="card"><div class="grid-4">
    @can('orders.discount')
      <div class="field"><label>Remise globale HT (DH)</label><input type="number" step="0.01" min="0" name="manual_discount"></div>
      <div class="field"><label>Motif de la remise</label><input name="discount_reason"></div>
    @endcan
    @can('payments.record')
      <div class="field"><label>Acompte encaissé (DH)</label><input type="number" step="0.01" min="0" name="deposit"></div>
      <div class="field"><label>Moyen</label><select name="deposit_method">@foreach(config('hocreative.payment_methods') as $m)<option value="{{ $m }}">{{ __('payment.'.$m) }}</option>@endforeach</select></div>
    @endcan
  </div>
  <div class="grid-2"><div class="field"><label>Note visible client</label><textarea name="notes"></textarea></div><div class="field"><label>Note interne</label><textarea name="internal_notes"></textarea></div></div>
  <button class="btn btn-accent">Créer la commande</button>
  <p class="small muted">Les prix des produits catalogue sont recalculés par le serveur. Toute remise est tracée dans le journal d'audit avec son motif.</p></div>
</form>
@endsection
