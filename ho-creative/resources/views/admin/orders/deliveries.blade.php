@extends('layouts.admin')
@section('title', 'Livraisons')
@section('content')
<h1>Livraisons & retraits</h1>
<div class="card table-wrap"><table><thead><tr><th>Commande</th><th>Client</th><th>Mode</th><th>Avancement</th><th>Date estimée</th><th>Expéditions</th></tr></thead><tbody>
@forelse($ready as $o)<tr><td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></td><td>{{ $o->customer?->displayName() }}<div class="small muted">{{ $o->contact_phone }}</div></td>
  <td>{{ $o->shipping_method === 'pickup' ? 'Retrait' : ($o->shippingZone?->t('name') ?? 'Livraison') }}<div class="small muted">{{ $o->shipping_address['city'] ?? '' }}</div></td><td><span class="pill">{{ __('fulfillment.'.$o->fulfillment_status) }}</span></td><td>{{ d($o->estimated_delivery_at) }}</td>
  <td class="small">@foreach($o->shipments as $s){{ __('shipment_status.'.$s->status) }} {{ $s->tracking_number }}<br>@endforeach</td></tr>
@empty<tr><td colspan="6" class="muted">Rien à expédier.</td></tr>@endforelse
</tbody></table>{{ $ready->links('partials.pagination') }}</div>
@endsection
