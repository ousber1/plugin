@extends('layouts.admin')
@section('title', $def['label'])
@section('content')
<div class="page-head"><h1>{{ $def['label'] }}</h1>
  <div class="flex"><a class="btn btn-sm btn-ghost" href="{{ route('admin.r.export', $def['key']) }}">⬇ CSV</a><a class="btn btn-sm" href="{{ route('admin.r.create', $def['key']) }}">+ Ajouter</a></div></div>
@if(! empty($def['note']))<div class="alert alert-info small">{{ $def['note'] }}</div>@endif
<form class="toolbar"><div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher…"></div><button class="btn btn-sm btn-ghost">Filtrer</button></form>
<div class="card table-wrap"><table>
  <thead><tr>@foreach($def['columns'] as $c => $label)<th>{{ $label }}</th>@endforeach<th></th></tr></thead>
  <tbody>
  @forelse($rows as $m)
    <tr>
      @foreach($def['columns'] as $c => $label)<td>{{ \App\Http\Controllers\Admin\ResourceController::cell($def, $m, $c) }}@if($loop->first && ($m->is_demo ?? false)) <span class="pill pill-demo">DÉMO</span>@endif</td>@endforeach
      <td class="right" style="white-space:nowrap">
        @if(! empty($def['show']))<a class="btn btn-sm btn-ghost" href="{{ route($def['show'], $m->id) }}">Fiche</a>@endif
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.r.edit', [$def['key'], $m->id]) }}">Modifier</a>
        <form method="post" action="{{ route('admin.r.destroy', [$def['key'], $m->id]) }}" style="display:inline" data-confirm="Supprimer définitivement ?">@csrf @method('delete')<button class="btn btn-sm btn-ghost">✕</button></form>
      </td>
    </tr>
  @empty
    <tr><td colspan="{{ count($def['columns']) + 1 }}" class="muted">Aucun élément.</td></tr>
  @endforelse
  </tbody>
</table>{{ $rows->links('partials.pagination') }}</div>
@endsection
