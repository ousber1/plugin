@extends('layouts.admin')
@section('title', 'Clients')
@section('content')
<div class="page-head"><h1>Clients</h1><a class="btn btn-sm" href="{{ route('admin.customers.create') }}">+ Nouveau client</a></div>
<form class="toolbar"><div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, société, e-mail, téléphone, ICE"></div>
  <div class="field"><select name="type"><option value="">Tous</option><option value="individual" @selected(request('type') === 'individual')>Particuliers</option><option value="company" @selected(request('type') === 'company')>Entreprises</option></select></div><button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table>
  <thead><tr><th>Client</th><th>Type</th><th>Contact</th><th>Ville</th><th>Commandes</th><th>Origine</th></tr></thead>
  <tbody>@foreach($customers as $c)
    <tr><td><a href="{{ route('admin.customers.show', $c) }}"><strong>{{ $c->displayName() }}</strong></a> @if($c->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ $c->type === 'company' ? 'Entreprise' : 'Particulier' }}</td><td class="small">{{ $c->phone }}<br>{{ $c->email }}</td><td>{{ $c->city }}</td><td>{{ $c->orders_count }}</td><td>{{ __('channel.'.$c->source) }}</td></tr>
  @endforeach</tbody></table>{{ $customers->links('partials.pagination') }}</div>
@endsection
