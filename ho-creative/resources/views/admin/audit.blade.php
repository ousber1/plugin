@extends('layouts.admin')
@section('title', 'Journal d\'audit')
@section('content')
<h1>Journal d'audit</h1>
<form class="toolbar"><div class="field"><select name="action"><option value="">Toutes les actions</option>@foreach(['order', 'payment', 'proof', 'production', 'stock', 'pricing', 'staff', 'settings', 'quote', 'document', 'artwork', 'purchase'] as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div><button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table class="small"><thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Avant</th><th>Après</th><th>Motif</th><th>IP</th></tr></thead><tbody>
@foreach($logs as $l)<tr><td style="white-space:nowrap">{{ d($l->created_at, true) }}</td><td>{{ $l->user?->name ?? 'Client / système' }}</td><td><strong>{{ $l->action }}</strong></td><td>{{ class_basename($l->auditable_type) }} #{{ $l->auditable_id }}</td>
  <td class="code">{{ \Illuminate\Support\Str::limit(json_encode($l->old_values, JSON_UNESCAPED_UNICODE), 120) }}</td><td class="code">{{ \Illuminate\Support\Str::limit(json_encode($l->new_values, JSON_UNESCAPED_UNICODE), 160) }}</td><td>{{ $l->reason }}</td><td>{{ $l->ip }}</td></tr>@endforeach
</tbody></table>{{ $logs->links('partials.pagination') }}</div>
@endsection
