@extends('layouts.admin')
@section('title', $customer->displayName())
@section('content')
<div class="page-head"><h1>{{ $customer->displayName() }} @if($customer->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</h1>
  <div class="flex"><a class="btn btn-sm btn-ghost" href="{{ route('admin.customers.edit', $customer) }}">Modifier</a><a class="btn btn-sm" href="{{ route('admin.quotes.create', ['customer' => $customer->id]) }}">+ Devis</a><a class="btn btn-sm" href="{{ route('admin.orders.create', ['customer' => $customer->id]) }}">+ Commande</a></div></div>
<div class="split">
  <div>
    <div class="card table-wrap"><h3>Commandes</h3><table><thead><tr><th>N°</th><th>Date</th><th>Canal</th><th>Statut</th><th>Paiement</th>@can('finance.view')<th class="num">Total</th><th class="num">Solde</th>@endcan</tr></thead><tbody>
      @forelse($customer->orders as $o)<tr><td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></td><td>{{ d($o->placed_at) }}</td><td>{{ __('channel.'.$o->channel) }}</td><td>{{ __('order_status.'.$o->status) }}</td><td>{{ __('payment_status.'.$o->payment_status) }}</td>@can('finance.view')<td class="num">{{ money($o->total) }}</td><td class="num">{{ money($o->balance()) }}</td>@endcan</tr>@empty<tr><td colspan="7" class="muted">Aucune commande.</td></tr>@endforelse</tbody></table></div>
    <div class="card table-wrap"><h3>Devis</h3><table><tbody>@forelse($customer->quotes as $q)<tr><td><a href="{{ route('admin.quotes.show', $q) }}">{{ $q->number }}</a></td><td>{{ __('quote_status.'.$q->status) }}</td><td class="num">{{ money($q->currentRevision?->total) }}</td></tr>@empty<tr><td class="muted">Aucun devis.</td></tr>@endforelse</tbody></table></div>
    @can('finance.view')<div class="card table-wrap"><h3>Paiements</h3><table><tbody>@forelse($payments as $p)<tr><td>{{ d($p->paid_at) }}</td><td>{{ $p->type === 'refund' ? 'Remboursement' : 'Paiement' }} – {{ __('payment.'.$p->method) }}</td><td class="num">{{ money($p->amount) }}</td></tr>@empty<tr><td class="muted">Aucun paiement.</td></tr>@endforelse</tbody></table></div>@endcan
    <div class="card"><h3>Notes internes</h3>
      @foreach($customer->customerNotes->sortByDesc('created_at') as $n)<p class="small"><strong>{{ $n->user?->name }}</strong> · {{ d($n->created_at, true) }}<br>{{ $n->body }}</p>@endforeach
      <form method="post" action="{{ route('admin.customers.notes', $customer) }}">@csrf<textarea name="body" required></textarea><button class="btn btn-sm" style="margin-top:6px">Ajouter</button></form></div>
  </div>
  <div>
    <div class="card"><dl class="dl">
      <dt>Type</dt><dd>{{ $customer->type === 'company' ? 'Entreprise' : 'Particulier' }}</dd><dt>Téléphone</dt><dd>{{ $customer->phone }}</dd>
      <dt>WhatsApp</dt><dd>@if($customer->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $customer->whatsapp) }}" target="_blank">{{ $customer->whatsapp }}</a>@endif</dd><dt>E-mail</dt><dd>{{ $customer->email }}</dd>
      <dt>ICE</dt><dd>{{ $customer->ice }}</dd><dt>RC / IF</dt><dd>{{ $customer->rc }} / {{ $customer->if_number }}</dd><dt>Ville</dt><dd>{{ $customer->city }}</dd>
      @can('finance.view')<dt>Solde dû</dt><dd><strong>{{ money($balance) }}</strong></dd>@endcan
      <dt>Compte web</dt><dd>{{ $customer->users->pluck('email')->implode(', ') ?: '—' }}</dd>
    </dl>@if($customer->notes)<p class="small">{{ $customer->notes }}</p>@endif</div>
    <div class="card"><h3>Contacts</h3>
      @foreach($customer->contacts as $ct)<p class="small"><strong>{{ $ct->name }}</strong> {{ $ct->position }}<br>{{ $ct->phone }} {{ $ct->email }}</p>@endforeach
      <form method="post" action="{{ route('admin.customers.contacts', $customer) }}" class="stack">@csrf<input name="name" placeholder="Nom" required><input name="position" placeholder="Fonction"><input name="phone" placeholder="Téléphone"><input name="email" type="email" placeholder="E-mail"><button class="btn btn-sm">Ajouter le contact</button></form></div>
    <div class="card"><h3>Adresses</h3>
      @foreach($customer->addresses as $a)<p class="small"><strong>{{ $a->label ?: ($a->type === 'billing' ? 'Facturation' : 'Livraison') }}</strong><br>{{ $a->name }} – {{ $a->line1 }}, {{ $a->city }}</p>@endforeach
      <details><summary class="small">+ Ajouter une adresse</summary><form method="post" action="{{ route('admin.customers.addresses', $customer) }}" class="stack">@csrf<select name="type"><option value="shipping">Livraison</option><option value="billing">Facturation</option></select><input name="label" placeholder="Libellé"><input name="name" placeholder="Nom" required value="{{ $customer->name }}"><input name="line1" placeholder="Adresse" required><input name="city" placeholder="Ville" required><input name="phone" placeholder="Téléphone"><button class="btn btn-sm">Ajouter</button></form></details></div>
  </div>
</div>
@endsection
