@extends('layouts.admin')
@section('title', 'Devis')
@section('content')
<div class="page-head"><h1>Devis</h1><a class="btn btn-sm" href="{{ route('admin.quotes.create') }}">+ Nouveau devis</a></div>
<form class="toolbar"><div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="N°, client"></div>
  <div class="field"><select name="status"><option value="">Tous statuts</option>@foreach(['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __('quote_status.'.$s) }}</option>@endforeach</select></div><button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table>
  <thead><tr><th>N°</th><th>Client</th><th>Canal</th><th>Rév.</th><th>Statut</th><th>Expire</th><th class="num">Total TTC</th></tr></thead>
  <tbody>@foreach($quotes as $q)<tr><td><a href="{{ route('admin.quotes.show', $q) }}">{{ $q->number }}</a> @if($q->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ $q->customer->displayName() }}</td><td>{{ __('channel.'.$q->channel) }}</td><td>{{ $q->current_revision }}</td><td><span class="pill">{{ __('quote_status.'.$q->status) }}</span></td><td>{{ d($q->expires_at) }}</td><td class="num">{{ money($q->currentRevision?->total) }}</td></tr>@endforeach</tbody>
</table>{{ $quotes->links('partials.pagination') }}</div>
@endsection
