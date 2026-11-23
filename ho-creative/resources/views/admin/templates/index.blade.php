@extends('layouts.admin')
@section('title', 'Modèles')
@section('content')
<div class="page-head"><h1>Modèles personnalisables</h1><a class="btn btn-sm" href="{{ route('admin.templates.create') }}">+ Nouveau modèle</a></div>
<div class="card table-wrap"><table>
  <thead><tr><th></th><th>Nom</th><th>Produit</th><th>Format</th><th>Statut</th><th></th></tr></thead>
  <tbody>@foreach($templates as $t)
    <tr><td><img src="{{ $t->thumbnail ?: '/images/placeholder.svg' }}" alt="" style="height:48px;border-radius:6px"></td><td>{{ $t->t('name') }} @if($t->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</td><td>{{ $t->product?->t('name') }}</td><td>{{ (float) $t->width_mm }}×{{ (float) $t->height_mm }} mm · {{ $t->sides }} face(s)</td>
      <td>{{ $t->is_published ? 'Publié' : 'Brouillon' }}</td>
      <td class="right"><a class="btn btn-sm btn-ghost" href="{{ route('admin.templates.edit', $t) }}">Éditer</a>
        <form method="post" action="{{ route('admin.templates.update', $t) }}" style="display:inline">@csrf @method('put')<input type="hidden" name="publish" value="{{ $t->is_published ? 0 : 1 }}"><button class="btn btn-sm btn-ghost">{{ $t->is_published ? 'Dépublier' : 'Publier' }}</button></form></td></tr>
  @endforeach</tbody></table>{{ $templates->links('partials.pagination') }}</div>
@endsection
