@extends('layouts.store')
@section('title', __('Demande de devis sur mesure'))
@section('content')
<div class="container section" style="padding-top:20px;max-width:900px">
  <h1>{{ __('Demande de devis sur mesure') }}</h1>
  <p class="muted">{{ __('Grand format, packaging, quantités importantes ou projet spécial : décrivez votre besoin, nous vous répondons avec un devis détaillé.') }}</p>
  <form class="card" method="post" action="{{ route('quote.request.store') }}" enctype="multipart/form-data" data-once>@csrf
    <div class="grid-2">
      <div class="field"><label for="name">{{ __('Nom complet') }} *</label><input id="name" name="name" required value="{{ old('name', auth()->user()?->name) }}"></div>
      <div class="field"><label for="company">{{ __('Société') }}</label><input id="company" name="company" value="{{ old('company') }}"></div>
      <div class="field"><label for="email">{{ __('E-mail') }} *</label><input id="email" type="email" name="email" required value="{{ old('email', auth()->user()?->email) }}"></div>
      <div class="field"><label for="phone">{{ __('Téléphone / WhatsApp') }} *</label><input id="phone" type="tel" name="phone" required value="{{ old('phone', auth()->user()?->phone) }}"></div>
    </div>
    <div class="grid-2">
      <div class="field"><label for="product_type">{{ __('Type de produit') }} *</label><input id="product_type" name="product_type" required list="ptypes" value="{{ old('product_type', $product?->t('name')) }}">
        <datalist id="ptypes">@foreach($products as $p)<option value="{{ $p->t('name') }}">@endforeach</datalist>
        <input type="hidden" name="product_id" value="{{ old('product_id', $product?->id) }}"></div>
      <div class="field"><label for="quantity">{{ __('Quantité') }} *</label><input id="quantity" type="number" min="1" name="quantity" required value="{{ old('quantity', 1) }}"></div>
    </div>
    <div class="grid-3">
      <div class="field"><label for="width">{{ __('Largeur') }}</label><input id="width" type="number" step="0.1" min="0" name="width" value="{{ old('width') }}"></div>
      <div class="field"><label for="height">{{ __('Hauteur') }}</label><input id="height" type="number" step="0.1" min="0" name="height" value="{{ old('height') }}"></div>
      <div class="field"><label for="unit">{{ __('Unité') }}</label><select id="unit" name="unit"><option value="mm">mm</option><option value="cm" @selected(old('unit') === 'cm')>cm</option></select></div>
    </div>
    <div class="grid-2">
      <div class="field"><label for="material">{{ __('Matière / support') }}</label><input id="material" name="material" value="{{ old('material') }}" placeholder="{{ __('ex. bâche 510 g, Dibond 3 mm…') }}"></div>
      <div class="field"><label for="finishes">{{ __('Finitions') }}</label><input id="finishes" name="finishes" value="{{ old('finishes') }}" placeholder="{{ __('ex. pelliculage mat, œillets, découpe…') }}"></div>
      <div class="field"><label for="deadline">{{ __('Date souhaitée') }}</label><input id="deadline" type="date" name="deadline" min="{{ today()->toDateString() }}" value="{{ old('deadline') }}"></div>
      <div class="field"><label for="city">{{ __('Ville de livraison') }}</label><input id="city" name="city" value="{{ old('city') }}"></div>
    </div>
    <div class="field"><label for="notes">{{ __('Détails du projet') }}</label><textarea id="notes" name="notes">{{ old('notes') }}</textarea></div>
    <div class="field"><label for="att">{{ __('Pièces jointes (max 5 fichiers : PDF, PNG, JPEG, AI, EPS, ZIP)') }}</label><input id="att" type="file" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg,.ai,.eps,.zip"></div>
    <div class="sr-only" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
    <button class="btn btn-accent">{{ __('Envoyer ma demande') }}</button>
    @if(setting('contact.whatsapp'))<a class="btn btn-wa" href="https://wa.me/{{ setting('contact.whatsapp') }}" rel="noopener" target="_blank">{{ __('Ou écrivez-nous sur WhatsApp') }}</a>@endif
  </form>
</div>
@endsection
