@extends('layouts.store')
@section('title', __('Panier'))
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')
<div class="container section" style="padding-top:20px">
  <h1>{{ __('Votre panier') }}</h1>
  @if(empty($priced['lines']))
    <div class="card center"><p>{{ __('Votre panier est vide.') }}</p><a class="btn btn-accent" href="{{ route('home') }}">{{ __('Découvrir nos produits') }}</a></div>
  @else
  <div class="split">
    <div>
      @foreach($priced['lines'] as $l)
        @php($item = $l['item'])
        <div class="card">
          <div class="flex between" style="align-items:flex-start">
            <div class="flex" style="align-items:flex-start;flex-wrap:nowrap">
              <img src="{{ $l['product']?->image() }}" alt="" width="90" style="border-radius:10px;border:1px solid var(--line)">
              <div>
                <h3 style="margin:0">{{ $l['product']?->t('name') }}</h3>
                @if($l['valid'])
                  <p class="small muted" style="margin:.2em 0">{{ $l['price']->describe() }}</p>
                  <p class="small" style="margin:0">⏱ {{ __('Production : :n jour(s) ouvré(s)', ['n' => $l['price']->productionDays]) }}</p>
                @else
                  <div class="alert alert-err small" style="margin:6px 0">{{ implode(' ', $l['errors']) }}</div>
                @endif
              </div>
            </div>
            <div class="right"><div style="font-size:1.2rem;font-weight:800">{{ money($l['total']) }}</div><div class="small muted">{{ __('HT') }}</div></div>
          </div>
          <div class="flex between" style="margin-top:12px">
            <form method="post" action="{{ route('cart.update', $item) }}" class="flex">@csrf @method('patch')
              <label for="q{{ $item->id }}" class="small" style="margin:0">{{ __('Quantité') }}</label>
              <input id="q{{ $item->id }}" type="number" name="quantity" min="1" value="{{ $item->selection['quantity'] ?? 1 }}" style="width:110px">
              <button class="btn btn-sm btn-ghost">{{ __('Mettre à jour') }}</button>
            </form>
            <form method="post" action="{{ route('cart.remove', $item) }}">@csrf @method('delete')<button class="btn btn-sm btn-ghost">✕ {{ __('Retirer') }}</button></form>
          </div>
          <div style="margin-top:10px" class="small">
            <strong>{{ __('Fichier') }} :</strong>
            @switch($item->artwork_mode)
              @case('upload') {{ __('Fichier fourni') }} @break
              @case('later') {{ __('À envoyer après la commande') }} @break
              @case('template') {{ __('Modèle personnalisé') }} @break
              @case('editor') {{ __('Création en ligne') }} @break
              @case('design_service') {{ __('Création par HO CREATIVE') }} @break
            @endswitch
            @if(in_array($item->artwork_mode, ['template', 'editor']))
              @if($item->design && $item->design->exports)<span class="pill pill-ok">{{ __('Design validé') }}</span>@else<span class="pill pill-warn">{{ __('Design non finalisé') }}</span>@endif
              <a href="{{ route('editor', ['item' => $item->id]) }}">{{ __('Ouvrir l\'éditeur') }}</a>
            @endif
            @foreach($item->artworkFiles as $f)
              <div>📄 {{ $f->side === 'back' ? __('Verso') : ($f->side === 'reference' ? __('Référence') : __('Recto')) }} : <a href="{{ route('files.artwork', $f) }}" target="_blank">{{ $f->original_name }}</a> (v{{ $f->version }})</div>
              @include('partials.artwork-checks', ['file' => $f])
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
    <div class="card">
      <h2 style="font-size:1.2rem">{{ __('Récapitulatif') }}</h2>
      <form method="post" action="{{ route('cart.coupon') }}" class="flex" style="margin-bottom:12px">@csrf
        <label for="coupon" class="sr-only">{{ __('Code promo') }}</label>
        <input id="coupon" name="code" placeholder="{{ __('Code promo') }}" value="{{ $cart?->coupon_code }}" class="grow" style="width:auto;flex:1">
        <button class="btn btn-sm btn-ghost">{{ __('Appliquer') }}</button>
      </form>
      @if($priced['totals']['coupon_error'])<p class="error-text">{{ $priced['totals']['coupon_error'] }}</p>@endif
      @include('store._totals', ['t' => $priced['totals'], 'shippingNote' => __('Calculée à l\'étape suivante')])
      @if($priced['has_invalid'])
        <div class="alert alert-err small">{{ __('Retirez ou modifiez les articles indisponibles pour continuer.') }}</div>
      @else
        <a class="btn btn-accent btn-block" href="{{ route('checkout') }}">{{ __('Passer commande') }}</a>
      @endif
      <p class="small muted" style="margin-top:8px">{{ __('Les prix sont recalculés par nos serveurs à chaque étape.') }}</p>
    </div>
  </div>
  @endif
</div>
@endsection
