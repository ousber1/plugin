@extends('layouts.admin')
@section('title', 'Demandes de devis')
@section('content')
<div class="page-head"><h1>Demandes de devis</h1>
  <div class="flex">@foreach(['' => 'Toutes', 'new' => 'Nouvelles', 'in_progress' => 'En cours', 'quoted' => 'Chiffrées', 'closed' => 'Closes'] as $k => $v)<a class="btn btn-sm {{ request('status', '') === $k ? '' : 'btn-ghost' }}" href="?status={{ $k }}">{{ $v }}</a>@endforeach</div></div>
<div class="card table-wrap"><table><thead><tr><th>Date</th><th>Demandeur</th><th>Produit</th><th>Qté</th><th>Échéance</th><th>Statut</th></tr></thead><tbody>
@foreach($reqs as $r)<tr><td>{{ d($r->created_at, true) }}</td><td><a href="{{ route('admin.quote-requests.show', $r) }}">{{ $r->name }}</a><div class="small muted">{{ $r->company }} {{ $r->phone }}</div></td><td>{{ $r->product_type }}</td><td>{{ $r->quantity }}</td><td>{{ d($r->deadline) }}</td><td><span class="pill">{{ $r->status }}</span></td></tr>@endforeach
</tbody></table>{{ $reqs->links('partials.pagination') }}</div>
@endsection
