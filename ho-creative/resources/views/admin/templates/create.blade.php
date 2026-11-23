@extends('layouts.admin')
@section('title', 'Nouveau modèle')
@section('content')
<h1>Nouveau modèle</h1>
<form class="card" method="post" action="{{ route('admin.templates.store') }}" style="max-width:700px">@csrf
  <div class="grid-2"><div class="field"><label>Nom (FR)</label><input name="name[fr]" required></div><div class="field"><label>Nom (AR)</label><input name="name[ar]" dir="rtl"></div></div>
  <div class="field"><label>Produit</label><select name="product_id"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->t('name') }}</option>@endforeach</select></div>
  <div class="grid-4">
    <div class="field"><label>Largeur (mm)</label><input type="number" step="0.1" name="width_mm" value="85" required></div><div class="field"><label>Hauteur (mm)</label><input type="number" step="0.1" name="height_mm" value="55" required></div>
    <div class="field"><label>Fond perdu (mm)</label><input type="number" step="0.1" name="bleed_mm" value="3" required></div><div class="field"><label>Marge sécurité (mm)</label><input type="number" step="0.1" name="safe_mm" value="4" required></div>
  </div>
  <div class="field"><label>Faces</label><select name="sides"><option value="1">Recto</option><option value="2">Recto / verso</option></select></div>
  <button class="btn btn-accent">Créer et ouvrir l'éditeur</button>
</form>
@endsection
