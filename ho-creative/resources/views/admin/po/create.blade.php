@extends('layouts.admin')
@section('title', 'Nouveau bon de commande')
@section('content')
<h1>Nouveau bon de commande</h1>
<form method="post" action="{{ route('admin.po.store') }}" data-once>@csrf
  <div class="card"><div class="grid-3">
    <div class="field"><label>Fournisseur *</label><select name="supplier_id" required>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected($supplierId == $s->id)>{{ $s->name }}</option>@endforeach</select><a class="small" href="{{ route('admin.r.create', 'suppliers') }}">+ fournisseur</a></div>
    <div class="field"><label>Livraison prévue</label><input type="date" name="expected_at"></div>
    <div class="field"><label>Réf. facture / BL fournisseur</label><input name="supplier_reference"></div>
    <div class="field" style="grid-column:1/-1"><label>Notes</label><input name="notes"></div>
  </div></div>
  <div data-rows class="card"><p class="small muted">Chaque ligne : un <b>article du magasin</b> (entrera dans le stock de la boutique à la réception), une <b>matière atelier</b>, ou une <b>sous-traitance</b> liée à un travail.</p>
    <div data-rows-list>@include('admin.po._item', ['i' => 0])</div>
    <template>@include('admin.po._item', ['i' => '__i__'])</template>
    <button type="button" class="btn btn-sm btn-ghost" data-rows-add>+ Ligne</button>
  </div>
  <button class="btn btn-accent">Créer</button>
</form>
@endsection
