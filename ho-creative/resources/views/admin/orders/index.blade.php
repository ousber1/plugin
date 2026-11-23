@extends('layouts.admin')
@section('title', 'Commandes')
@section('content')
@php($money = auth()->user()->can('finance.view') || auth()->user()->can('orders.manage'))
<div class="page-head"><h1>Commandes</h1>@can('orders.manage')<a class="btn btn-sm" href="{{ route('admin.orders.create') }}">+ Commande comptoir / téléphone / WhatsApp</a>@endcan</div>
<form class="toolbar">
  <div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="N°, client, téléphone"></div>
  <div class="field"><select name="status"><option value="">Statut</option>@foreach(['pending', 'confirmed', 'in_production', 'ready', 'completed', 'cancelled'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __('order_status.'.$s) }}</option>@endforeach</select></div>
  <div class="field"><select name="channel"><option value="">Canal</option>@foreach(config('hocreative.order_channels') as $s)<option value="{{ $s }}" @selected(request('channel') === $s)>{{ __('channel.'.$s) }}</option>@endforeach</select></div>
  <div class="field"><select name="payment"><option value="">Paiement</option>@foreach(['unpaid', 'partial', 'paid', 'refunded', 'partially_refunded'] as $s)<option value="{{ $s }}" @selected(request('payment') === $s)>{{ __('payment_status.'.$s) }}</option>@endforeach</select></div>
  <button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table>
  <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Canal</th><th>Statut</th><th>Paiement</th><th>Livraison</th>@if($money)<th class="num">Total</th><th class="num">Solde</th>@endif</tr></thead>
  <tbody>@forelse($orders as $o)
    <tr><td><a href="{{ route('admin.orders.show', $o) }}"><strong>{{ $o->number }}</strong></a> @if($o->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ d($o->placed_at) }}</td><td>{{ $o->customer?->displayName() }}</td><td>{{ __('channel.'.$o->channel) }}</td>
      <td><span class="pill">{{ __('order_status.'.$o->status) }}</span></td><td><span class="pill {{ $o->payment_status === 'paid' ? 'pill-ok' : ($o->payment_status === 'unpaid' ? 'pill-warn' : '') }}">{{ __('payment_status.'.$o->payment_status) }}</span></td>
      <td>{{ __('fulfillment.'.$o->fulfillment_status) }}<div class="small muted">{{ d($o->estimated_delivery_at) }}</div></td>@if($money)<td class="num">{{ money($o->total) }}</td><td class="num">{{ money($o->balance()) }}</td>@endif</tr>
  @empty<tr><td colspan="9" class="muted">Aucune commande.</td></tr>@endforelse</tbody>
</table>{{ $orders->links('partials.pagination') }}</div>
@endsection
