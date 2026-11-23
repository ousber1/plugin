@extends('layouts.store')
@section('title', __('Éditeur en ligne'))
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')
<div class="container section" style="padding-top:20px">
  <div class="flex between"><h1 style="font-size:1.5rem;margin:0">{{ __('Éditeur en ligne') }} @if($product)– {{ $product->t('name') }}@endif</h1>
    <span class="muted small">{{ $config['width_mm'] }} × {{ $config['height_mm'] }} mm · {{ __('fond perdu') }} {{ $config['bleed_mm'] }} mm</span></div>
  <div class="editor-legend small" style="margin:8px 0 12px">
    <span><i style="border-color:#E4007C"></i>{{ __('Ligne de coupe') }}</span>
    <span><i style="border-color:#00A0E3"></i>{{ __('Zone de sécurité (textes importants à l\'intérieur)') }}</span>
    <span><i style="border-color:rgba(228,0,124,.3);border-top-width:8px;border-top-style:solid"></i>{{ __('Fond perdu (sera coupé)') }}</span>
  </div>
  <div id="editor" class="editor-shell" data-config="{{ json_encode($config) }}"
       data-i18n="{{ json_encode(['sample' => __('Votre texte'), 'too_big' => __('Image trop lourde (max 15 Mo).'), 'low_res' => __('Attention : résolution de l\'image probablement insuffisante pour cette taille.'), 'unsaved' => __('Modifications non enregistrées'), 'saving' => __('Enregistrement…'), 'exporting' => __('Export haute définition en cours…'), 'saved' => __('Enregistré'), 'error' => __('Erreur lors de l\'enregistrement'), 'confirm_finish' => __('Valider ce design ? Il sera exporté et joint à votre article. Notre prépresse le vérifiera avant impression.')]) }}">
    <div class="card editor-panel">
      <h3>{{ __('Ajouter') }}</h3>
      <button class="btn btn-sm" id="add-text" type="button">T {{ __('Texte') }}</button>
      <label class="btn btn-sm btn-ghost" for="add-image" style="margin-bottom:6px">🖼 {{ __('Image / logo') }}</label>
      <input id="add-image" type="file" accept="image/png,image/jpeg" class="sr-only">
      <button class="btn btn-sm btn-ghost" id="add-rect" type="button">▭ {{ __('Forme') }}</button>
      @if($config['sides'] === 2)
        <h3 style="margin-top:12px">{{ __('Face') }}</h3>
        <div class="flex"><button class="btn btn-sm btn-ghost" type="button" data-side="front" aria-pressed="true">{{ __('Recto') }}</button><button class="btn btn-sm btn-ghost" type="button" data-side="back" aria-pressed="false">{{ __('Verso') }}</button></div>
      @endif
      <h3 style="margin-top:12px">{{ __('Historique') }}</h3>
      <div class="flex"><button class="btn btn-sm btn-ghost" id="undo" type="button" style="width:auto">↶ {{ __('Annuler') }}</button><button class="btn btn-sm btn-ghost" id="redo" type="button" style="width:auto">↷ {{ __('Rétablir') }}</button></div>
    </div>
    <div>
      <div class="editor-stage"><canvas id="editor-canvas"></canvas></div>
      <p id="status" class="small muted" role="status" aria-live="polite"></p>
    </div>
    <div class="card editor-panel">
      <h3>{{ __('Élément sélectionné') }}</h3>
      <label for="color" class="small">{{ __('Couleur') }}</label><input id="color" type="color" value="#1B1F3B">
      <label for="font" class="small" style="margin-top:6px">{{ __('Police') }}</label>
      <select id="font">@foreach(['Arial', 'Georgia', 'Verdana', 'Tahoma', 'Times New Roman', 'Courier New', 'Trebuchet MS'] as $f)<option>{{ $f }}</option>@endforeach</select>
      <label for="size" class="small" style="margin-top:6px">{{ __('Taille du texte (mm)') }}</label><input id="size" type="number" min="1" max="500" value="6">
      <label for="align" class="small" style="margin-top:6px">{{ __('Alignement') }}</label>
      <select id="align"><option value="left">{{ __('Gauche') }}</option><option value="center">{{ __('Centré') }}</option><option value="right">{{ __('Droite') }}</option></select>
      <div class="flex" style="margin-top:8px">
        <button class="btn btn-sm btn-ghost" id="bold" type="button" style="width:auto"><b>B</b></button>
        <button class="btn btn-sm btn-ghost" id="front-btn" type="button" style="width:auto" title="{{ __('Avancer') }}">⬆</button>
        <button class="btn btn-sm btn-ghost" id="back-btn" type="button" style="width:auto" title="{{ __('Reculer') }}">⬇</button>
        <button class="btn btn-sm btn-ghost" id="center-btn" type="button" style="width:auto" title="{{ __('Centrer') }}">⊕</button>
        <button class="btn btn-sm btn-danger" id="delete" type="button" style="width:auto">🗑</button>
      </div>
      <label for="bg" class="small" style="margin-top:10px">{{ __('Couleur de fond') }}</label><input id="bg" type="color" value="#ffffff">
      <hr>
      <button class="btn btn-ghost" id="save" type="button">💾 {{ __('Enregistrer le brouillon') }}</button>
      <button class="btn btn-accent" id="finish" type="button">✔ {{ __('Valider mon design') }}</button>
      <p class="help">{{ __('Export : image PNG jusqu\'à 300 dpi (RVB) intégrée dans un PDF au format exact avec fond perdu. La conversion CMJN et le contrôle final sont réalisés par notre prépresse ; un BAT vous sera soumis.') }}</p>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script src="/vendor/fabric/fabric.min.js" defer></script>
<script src="/assets/editor.js?v={{ filemtime(public_path('assets/editor.js')) }}" defer></script>
@endpush
