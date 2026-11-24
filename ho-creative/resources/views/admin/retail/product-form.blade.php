@extends('layouts.admin')
@section('title', 'Article')
@section('content')
<div class="page-head"><h1>@icon('tag') {{ $product->exists ? $product->name : 'Nouvel article' }}</h1><a class="btn btn-ghost" href="{{ $product->exists ? route('admin.retail.products.show', $product) : route('admin.retail.products.index') }}">Annuler</a></div>
<form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.retail.products.update', $product) : route('admin.retail.products.store') }}">@csrf @if($product->exists) @method('put') @endif
<div class="split">
  <div class="card">
    <div class="field"><label>Désignation *</label><input name="name" required value="{{ old('name', $product->name) }}" placeholder="ex. Ramette papier A4 80 g"></div>
    <div class="grid-2">
      <div class="field"><label>Référence (SKU) *</label><input name="sku" required value="{{ old('sku', $product->sku) }}"></div>
      <div class="field"><label>Code-barres (EAN)</label><input name="barcode" value="{{ old('barcode', $product->barcode) }}" placeholder="Scannez ici"><div class="help">Utilisé par la douchette en caisse.</div></div>
      <div class="field"><label>Rayon</label><select name="retail_category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('retail_category_id', $product->retail_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
      <div class="field"><label>Fournisseur habituel</label><select name="supplier_id"><option value="">—</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $product->supplier_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    </div>
    <div class="field"><label>Description</label><textarea name="description" rows="3">{{ old('description', $product->description) }}</textarea></div>
    <div class="field"><label>Photo</label>@if($product->image)<img src="{{ $product->image }}" alt="" style="height:70px;border-radius:10px;margin-bottom:6px">@endif<input type="file" name="image_file" accept="image/*"></div>
  </div>
  <div>
    <div class="card">
      <div class="card-head"><h3>Prix & TVA</h3></div>
      <div class="grid-2">
        <div class="field"><label>Prix de vente TTC (DH) *</label><input type="number" step="0.01" min="0" name="price_ttc" required value="{{ old('price_ttc', $product->price_ttc) }}" style="font-weight:700"></div>
        <div class="field"><label>TVA (%) *</label><input type="number" step="0.01" min="0" max="100" name="tax_rate" required value="{{ old('tax_rate', $product->tax_rate) }}"></div>
        <div class="field"><label>Unité</label><select name="unit">@foreach(['piece' => 'Pièce', 'pack' => 'Paquet', 'ream' => 'Ramette', 'kg' => 'kg', 'm' => 'Mètre', 'l' => 'Litre'] as $k => $l)<option value="{{ $k }}" @selected(old('unit', $product->unit) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Seuil d'alerte stock</label><input type="number" step="0.001" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 0) }}"></div>
      </div>
      @if($product->exists)<p class="small muted">Prix HT : {{ money($product->priceHt()) }} · coût moyen : {{ money(round((float) $product->cost_price, 2)) }} @if($product->marginPct() !== null)· marge {{ $product->marginPct() }} %@endif</p>@endif
      <input type="hidden" name="track_stock" value="0"><label class="check"><input type="checkbox" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock))> Suivre le stock</label>
      <input type="hidden" name="is_active" value="0"><label class="check" style="margin-top:8px"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> En vente (visible en caisse)</label>
    </div>
    @if(! $product->exists)
    <div class="card"><div class="card-head"><h3>Stock initial (facultatif)</h3></div>
      <div class="grid-2"><div class="field"><label>Quantité</label><input type="number" step="0.001" min="0" name="initial_stock"></div><div class="field"><label>Coût unitaire HT</label><input type="number" step="0.0001" min="0" name="initial_cost"></div></div>
      <p class="help">Ensuite, les entrées se font par bon de commande fournisseur (réception) ou par ajustement d'inventaire.</p></div>
    @endif
    <button class="btn btn-accent btn-lg btn-block">@icon('check') Enregistrer l'article</button>
  </div>
</div>
</form>
@endsection
