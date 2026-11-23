@extends('layouts.admin')
@section('title', 'Produit')
@section('content')
@php($C = \App\Http\Controllers\Admin\ProductController::class)
<div class="page-head"><h1>{{ $product->exists ? $product->t('name') : 'Nouveau produit' }}</h1>
  <div class="flex">@if($product->exists)<a class="btn btn-sm" href="{{ route('admin.pricing.edit', $product) }}">Options & prix →</a><a class="btn btn-sm btn-ghost" href="{{ route('product', $product->slug) }}" target="_blank">Voir ↗</a>@endif</div></div>
<form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">@csrf @if($product->exists) @method('put') @endif
<div class="split">
  <div>
    <div class="card">
      <div class="grid-2">
        <div class="field"><label>Nom (FR) *</label><input name="name[fr]" required value="{{ old('name.fr', $product->name['fr'] ?? '') }}"></div>
        <div class="field"><label>Nom (AR)</label><input name="name[ar]" dir="rtl" value="{{ old('name.ar', $product->name['ar'] ?? '') }}"></div>
        <div class="field"><label>Résumé (FR)</label><input name="summary[fr]" value="{{ old('summary.fr', $product->summary['fr'] ?? '') }}"></div>
        <div class="field"><label>Résumé (AR)</label><input name="summary[ar]" dir="rtl" value="{{ old('summary.ar', $product->summary['ar'] ?? '') }}"></div>
        <div class="field"><label>Description (FR, HTML simple)</label><textarea name="description[fr]" rows="7">{{ old('description.fr', $product->description['fr'] ?? '') }}</textarea></div>
        <div class="field"><label>Description (AR)</label><textarea name="description[ar]" rows="7" dir="rtl">{{ old('description.ar', $product->description['ar'] ?? '') }}</textarea></div>
      </div>
    </div>
    <div class="card">
      <div class="field"><label>Caractéristiques – une par ligne : <code>Libellé FR | Valeur FR | Libellé AR | Valeur AR</code></label><textarea name="specs_text" rows="5" class="code">{{ old('specs_text', $C::toLines($product->specs, 'specs')) }}</textarea></div>
      <div class="field"><label>Finitions – une par ligne : <code>FR | AR</code></label><textarea name="finishes_text" rows="4" class="code">{{ old('finishes_text', $C::toLines($product->finishes, 'finishes')) }}</textarea></div>
      <div class="field"><label>FAQ – une par ligne : <code>Question FR | Réponse FR | Question AR | Réponse AR</code></label><textarea name="faq_text" rows="5" class="code">{{ old('faq_text', $C::toLines($product->faq, 'faq')) }}</textarea></div>
    </div>
    <div class="card">
      <h3>Images</h3>
      <div class="flex">@foreach($product->images ?? [] as $img)<label class="check" style="flex-direction:column"><img src="{{ $img }}" alt="" style="height:70px;border-radius:8px"><span><input type="checkbox" name="images_keep[]" value="{{ $img }}" checked> garder</span></label>@endforeach</div>
      <input type="file" name="images_new[]" multiple accept="image/*">
      <h3 style="margin-top:12px">Guides d'impression (PDF)</h3>
      @foreach($product->guides ?? [] as $g)<label class="check"><input type="checkbox" name="guides_keep[]" value="{{ $g['path'] }}" checked> {{ tr($g['title']) }}</label>@endforeach
      <input type="file" name="guides_new[]" multiple accept="application/pdf">
    </div>
  </div>
  <div>
    <div class="card">
      <div class="field"><label>Catégorie *</label><select name="category_id" required>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->t('name') }}</option>@endforeach</select></div>
      <div class="field"><label>Slug (URL) *</label><input name="slug" required value="{{ old('slug', $product->slug) }}"></div>
      <div class="field"><label>Référence</label><input name="sku" value="{{ old('sku', $product->sku) }}"></div>
      <div class="field"><label>Mode de vente</label><select name="sale_mode">@foreach(\App\Models\Product::MODES as $m)<option value="{{ $m }}" @selected(old('sale_mode', $product->sale_mode) === $m)>{{ __('sale_mode.'.$m) }}</option>@endforeach</select>
        <div class="help">« Achat immédiat » nécessite une grille de prix publiée.</div></div>
      <fieldset style="border:0;padding:0"><legend class="small" style="font-weight:700">Parcours fichier proposés</legend>
        @foreach(\App\Models\Product::ARTWORK_MODES as $m)<label class="check"><input type="checkbox" name="artwork_modes[]" value="{{ $m }}" @checked(in_array($m, old('artwork_modes', $product->artwork_modes ?? [])))> {{ __('artwork_mode.'.$m) }}</label>@endforeach</fieldset>
      <div class="field" style="margin-top:8px"><label>Frais de création graphique (HT)</label><input type="number" step="0.01" min="0" name="design_fee" value="{{ old('design_fee', $product->design_fee ?? 0) }}"></div>
      <input type="hidden" name="requires_proof" value="0"><label class="check"><input type="checkbox" name="requires_proof" value="1" @checked(old('requires_proof', $product->requires_proof))> BAT obligatoire avant production</label>
      <input type="hidden" name="is_popular" value="0"><label class="check"><input type="checkbox" name="is_popular" value="1" @checked(old('is_popular', $product->is_popular))> Produit populaire (accueil)</label>
      <div class="field" style="margin-top:8px"><label>Ordre</label><input type="number" name="sort" value="{{ old('sort', $product->sort) }}"></div>
    </div>
    <div class="card">
      <h3>SEO</h3>
      <div class="field"><label>Titre (FR)</label><input name="meta_title[fr]" value="{{ $product->meta_title['fr'] ?? '' }}"></div>
      <div class="field"><label>Titre (AR)</label><input name="meta_title[ar]" dir="rtl" value="{{ $product->meta_title['ar'] ?? '' }}"></div>
      <div class="field"><label>Description (FR)</label><textarea name="meta_description[fr]" rows="2">{{ $product->meta_description['fr'] ?? '' }}</textarea></div>
      <div class="field"><label>Description (AR)</label><textarea name="meta_description[ar]" rows="2" dir="rtl">{{ $product->meta_description['ar'] ?? '' }}</textarea></div>
    </div>
    <button class="btn btn-accent btn-block">Enregistrer</button>
  </div>
</div>
</form>
@endsection
