@extends('account._layout')
@section('title', __('Factures'))
@section('account')
<h1>{{ __('Factures et documents') }}</h1>
<div class="card table-wrap"><table>
  <thead><tr><th>{{ __('Document') }}</th><th>{{ __('Numéro') }}</th><th>{{ __('Date') }}</th><th class="num">{{ __('Montant') }}</th><th></th></tr></thead>
  <tbody>@forelse($docs as $doc)
    <tr><td>{{ __('doc.'.$doc->type) }}</td><td>{{ $doc->number }}</td><td>{{ d($doc->issued_at) }}</td><td class="num">{{ money($doc->total) }}</td><td><a href="{{ route('files.document', $doc) }}" target="_blank">⬇ PDF</a></td></tr>
  @empty<tr><td colspan="5" class="muted">{{ __('Aucun document.') }}</td></tr>@endforelse</tbody>
</table>{{ $docs->links('partials.pagination') }}</div>
@endsection
