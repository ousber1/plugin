@extends('account._layout')
@section('title', __('Mes devis'))
@section('account')
<h1>{{ __('Mes devis') }}</h1>
<div class="card table-wrap"><table>
  <thead><tr><th>{{ __('Devis') }}</th><th>{{ __('Date') }}</th><th>{{ __('Validité') }}</th><th>{{ __('Statut') }}</th><th class="num">{{ __('Total TTC') }}</th></tr></thead>
  <tbody>@forelse($quotes as $q)
    <tr><td><a href="{{ route('quote.public', $q->token) }}"><strong>{{ $q->number }}</strong></a></td><td>{{ d($q->created_at) }}</td><td>{{ d($q->expires_at) }}</td><td><span class="pill">{{ __('quote_status.'.$q->status) }}</span></td><td class="num">{{ money($q->currentRevision?->total) }}</td></tr>
  @empty<tr><td colspan="5" class="muted">{{ __('Aucun devis.') }} <a href="{{ route('quote.request') }}">{{ __('Demander un devis') }}</a></td></tr>@endforelse</tbody>
</table>@if(method_exists($quotes, 'links')){{ $quotes->links('partials.pagination') }}@endif</div>
@endsection
