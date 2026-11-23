@extends('layouts.store')
@section('title', __('Créer un compte'))
@section('content')
<div class="container section" style="max-width:560px"><div class="card">
  <h1 style="font-size:1.5rem">{{ __('Créer un compte') }}</h1>
  <form method="post" action="{{ route('register') }}">@csrf
    <div class="grid-2">
      <div class="field"><label for="name">{{ __('Nom complet') }}</label><input id="name" name="name" required autocomplete="name" value="{{ old('name') }}"></div>
      <div class="field"><label for="phone">{{ __('Téléphone') }}</label><input id="phone" type="tel" name="phone" required autocomplete="tel" value="{{ old('phone') }}"></div>
    </div>
    <div class="field"><label for="email">{{ __('E-mail') }}</label><input id="email" type="email" name="email" required autocomplete="email" value="{{ old('email') }}"></div>
    <div class="field"><label for="company">{{ __('Société (optionnel)') }}</label><input id="company" name="company" value="{{ old('company') }}"></div>
    <div class="grid-2">
      <div class="field"><label for="password">{{ __('Mot de passe') }}</label><input id="password" type="password" name="password" required autocomplete="new-password"><div class="help">{{ __('8 caractères minimum, lettres et chiffres.') }}</div></div>
      <div class="field"><label for="password_confirmation">{{ __('Confirmation') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    </div>
    <button class="btn btn-accent btn-block">{{ __('Créer mon compte') }}</button>
  </form>
  <p class="small" style="margin-top:12px">{{ __('Déjà client ?') }} <a href="{{ route('login') }}">{{ __('Connexion') }}</a></p>
</div></div>
@endsection
