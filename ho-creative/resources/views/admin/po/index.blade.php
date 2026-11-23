@extends('layouts.admin')
@section('title', 'Bons de commande')
@section('content')
<div class="page-head"><h1>Bons de commande fournisseurs</h1><a class="btn btn-sm" href="{{ route('admin.po.create') }}">+ Nouveau</a></div>
<div class="card table-wrap"><table><thead><tr><th>N°</th><th>Fournisseur</th><th>Statut</th><th>Livraison prévue</th><th class="num">Total HT</th></tr></thead><tbody>
@foreach($pos as $po)<tr><td><a href="{{ route('admin.po.show', $po) }}">{{ $po->number }}</a></td><td>{{ $po->supplier->name }}</td><td><span class="pill">{{ __('po_status.'.$po->status) }}</span></td><td>{{ d($po->expected_at) }}</td><td class="num">{{ money($po->total) }}</td></tr>@endforeach
</tbody></table>{{ $pos->links('partials.pagination') }}</div>
@endsection
