@extends('layouts.admin')
@section('title', 'Paiements')
@section('content')
<h1>Paiements</h1>
<form class="toolbar"><div class="field"><select name="method"><option value="">Moyen</option>@foreach(config('hocreative.payment_methods') as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ __('payment.'.$m) }}</option>@endforeach</select></div>
  <div class="field"><select name="type"><option value="">Type</option><option value="payment" @selected(request('type') === 'payment')>Encaissements</option><option value="refund" @selected(request('type') === 'refund')>Remboursements</option></select></div><button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table><thead><tr><th>Date</th><th>Commande</th><th>Client</th><th>Type</th><th>Moyen</th><th>Réf.</th><th>Saisi par</th><th class="num">Montant</th></tr></thead><tbody>
@foreach($payments as $p)<tr><td>{{ d($p->paid_at, true) }}</td><td><a href="{{ route('admin.orders.show', $p->order) }}">{{ $p->order->number }}</a></td><td>{{ $p->order->customer?->displayName() }}</td><td>{{ $p->type === 'refund' ? 'Remboursement' : 'Encaissement' }}</td><td>{{ __('payment.'.$p->method) }} {{ $p->provider }}</td><td>{{ $p->reference }}</td><td>{{ $p->recorder?->name ?? 'Prestataire' }}</td><td class="num">{{ $p->type === 'refund' ? '−' : '' }}{{ money($p->amount) }}</td></tr>@endforeach
</tbody></table>{{ $payments->links('partials.pagination') }}</div>
@endsection
