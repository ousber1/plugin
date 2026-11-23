@extends('layouts.store')
@section('title', __('Commande'))
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')
@php($u = auth()->user())
@php($c = $u?->customer)
<div class="container section" style="padding-top:20px">
  <h1>{{ __('Finaliser la commande') }}</h1>
  @guest<div class="alert alert-info">{{ __('Vous avez un compte ?') }} <a href="{{ route('login') }}">{{ __('Connectez-vous') }}</a> {{ __('pour retrouver vos adresses et suivre vos commandes. Sinon, continuez en tant qu\'invité.') }}</div>@endguest
  <div class="split">
    <div>
      <form class="card" method="get" action="{{ route('checkout') }}">
        <h2 style="font-size:1.15rem">1. {{ __('Retrait ou livraison') }}</h2>
        <div class="choices" style="grid-template-columns:1fr 1fr">
          <label class="choice"><input type="radio" name="method" value="pickup" @checked($method === 'pickup') onchange="this.form.submit()"><span>🏪 {{ __('Retrait à l\'atelier') }}<small class="muted">{{ __('Gratuit') }}</small></span></label>
          <label class="choice"><input type="radio" name="method" value="delivery" @checked($method === 'delivery') onchange="this.form.submit()"><span>🚚 {{ __('Livraison au Maroc') }}</span></label>
        </div>
        @if($method === 'delivery')
          <div class="field" style="margin-top:12px"><label for="zone">{{ __('Zone de livraison') }}</label>
            <select id="zone" name="zone" onchange="this.form.submit()">
              @foreach($zones as $z)<option value="{{ $z->id }}" @selected($zone?->id === $z->id)>{{ $z->t('name') }} – {{ money($z->price) }} · {{ trans_choice(':n jour ouvré|:n jours ouvrés', $z->delivery_days, ['n' => $z->delivery_days]) }}@if($z->free_over) · {{ __('offerte dès :a', ['a' => money($z->free_over)]) }}@endif</option>@endforeach
            </select></div>
        @endif
        <noscript><button class="btn btn-sm">{{ __('Mettre à jour') }}</button></noscript>
      </form>

      <form class="card" method="post" action="{{ route('checkout.place') }}" data-once>@csrf
        <input type="hidden" name="checkout_key" value="{{ $key }}">
        <input type="hidden" name="shipping_method" value="{{ $method }}">
        <input type="hidden" name="zone_id" value="{{ $zone?->id }}">
        <h2 style="font-size:1.15rem">2. {{ __('Vos coordonnées') }}</h2>
        <div class="grid-2">
          <div class="field"><label for="name">{{ __('Nom complet') }} *</label><input id="name" name="name" required autocomplete="name" value="{{ old('name', $c?->name ?? $u?->name) }}"></div>
          <div class="field"><label for="phone">{{ __('Téléphone') }} *</label><input id="phone" type="tel" name="phone" required autocomplete="tel" value="{{ old('phone', $c?->phone ?? $u?->phone) }}"></div>
          <div class="field"><label for="email">{{ __('E-mail') }} *</label><input id="email" type="email" name="email" required autocomplete="email" value="{{ old('email', $c?->email ?? $u?->email) }}"></div>
          <div class="field"><label for="company">{{ __('Société (optionnel)') }}</label><input id="company" name="company" autocomplete="organization" value="{{ old('company', $c?->company_name) }}"></div>
          <div class="field"><label for="ice">{{ __('ICE (entreprises)') }}</label><input id="ice" name="ice" value="{{ old('ice', $c?->ice) }}"></div>
        </div>
        @if($method === 'delivery')
          <h2 style="font-size:1.15rem">3. {{ __('Adresse de livraison') }}</h2>
          @if($addresses->isNotEmpty())
            <p class="small">{{ __('Adresses enregistrées :') }} @foreach($addresses as $a)<button type="button" class="btn btn-sm btn-ghost" onclick="Object.entries({line1:@js($a->line1),line2:@js($a->line2),city:@js($a->city),postal_code:@js($a->postal_code)}).forEach(([k,v])=>document.getElementById(k).value=v||'')">{{ $a->label ?: $a->city }}</button>@endforeach</p>
          @endif
          <div class="field"><label for="line1">{{ __('Adresse') }} *</label><input id="line1" name="line1" required autocomplete="address-line1" value="{{ old('line1') }}"></div>
          <div class="field"><label for="line2">{{ __('Complément') }}</label><input id="line2" name="line2" autocomplete="address-line2" value="{{ old('line2') }}"></div>
          <div class="grid-2">
            <div class="field"><label for="city">{{ __('Ville') }} *</label><input id="city" name="city" required autocomplete="address-level2" value="{{ old('city') }}" list="cities">
              <datalist id="cities">@foreach((array) $zone?->cities as $city)<option value="{{ $city }}">@endforeach</datalist></div>
            <div class="field"><label for="postal_code">{{ __('Code postal') }}</label><input id="postal_code" name="postal_code" autocomplete="postal-code" value="{{ old('postal_code') }}"></div>
          </div>
        @endif
        <h2 style="font-size:1.15rem">{{ $method === 'delivery' ? '4' : '3' }}. {{ __('Paiement') }}</h2>
        @if(! $methods)<div class="alert alert-warn">{{ __('Aucun moyen de paiement n\'est activé. Contactez-nous.') }}</div>@endif
        <div class="artwork-modes">
          @foreach($methods as $i => $m)
            <label class="choice"><input type="radio" name="payment_method" value="{{ $m }}" @checked(old('payment_method', $methods[0] ?? null) === $m) required><span>{{ __('payment.'.$m) }} <small class="muted">{{ __('payment_help.'.$m) }}</small></span></label>
          @endforeach
        </div>
        <div class="field" style="margin-top:12px"><label for="notes">{{ __('Remarques (optionnel)') }}</label><textarea id="notes" name="notes">{{ old('notes') }}</textarea></div>
        <label class="check"><input type="checkbox" name="terms" value="1" required> {{ __('J\'accepte les conditions générales de vente.') }} <a href="{{ url('/page/cgv') }}" target="_blank">{{ __('Lire') }}</a></label>
        <button class="btn btn-accent btn-block" style="margin-top:14px" @disabled(! $methods)>{{ __('Confirmer la commande') }} – {{ money($priced['totals']['total']) }} {{ __('TTC') }}</button>
      </form>
    </div>
    <div class="card">
      <h2 style="font-size:1.15rem">{{ __('Votre commande') }}</h2>
      @foreach($priced['lines'] as $l)
        <div class="flex between small" style="border-bottom:1px solid var(--line);padding:6px 0"><span>{{ $l['product']?->t('name') }} × {{ $l['price']?->quantity }}</span><span>{{ money($l['total']) }}</span></div>
      @endforeach
      @include('store._totals', ['t' => $priced['totals']])
      @php($est = app(\App\Services\DeliveryEstimator::class)->estimate((int) collect($priced['lines'])->max(fn ($l) => $l['price']?->productionDays ?? 0), $zone, $method))
      <p class="small" style="margin-top:10px">📅 {{ $method === 'delivery' ? __('Livraison estimée') : __('Disponible à l\'atelier') }} : <strong>{{ d($est['delivery']) }}</strong><br>
        <span class="muted">{{ __('Estimation en jours ouvrés, à compter de la validation de votre fichier / BAT. Commandes reçues après :h traitées le jour ouvré suivant.', ['h' => setting('hours.cutoff')]) }}</span></p>
    </div>
  </div>
</div>
@endsection
