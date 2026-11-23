@extends('layouts.admin')
@section('title', 'Travaux')
@section('content')
<div class="page-head"><h1>Travaux de production</h1><a class="btn btn-sm btn-ghost" href="{{ route('admin.production.board') }}">Vue Kanban</a></div>
@include('admin.production._filters', ['withStage' => true])
<div class="card table-wrap"><table><thead><tr><th>Travail</th><th>Commande</th><th>Ligne</th><th>Étape</th><th>Priorité</th><th>Opérateur</th><th>Échéance</th></tr></thead><tbody>
@foreach($jobs as $j)<tr><td><a href="{{ route('admin.production.show', $j) }}">{{ $j->number }}</a></td><td>{{ $j->order->number }}</td><td class="small">{{ \Illuminate\Support\Str::limit($j->item->description, 60) }}</td><td><span class="pill">{{ __('stage.'.$j->stage) }}</span></td><td>{{ $j->priority }}</td><td>{{ $j->assignee?->name }}</td><td @class(['error-text' => $j->isOverdue()])>{{ d($j->due_at) }}</td></tr>@endforeach
</tbody></table>{{ $jobs->links('partials.pagination') }}</div>
@endsection
