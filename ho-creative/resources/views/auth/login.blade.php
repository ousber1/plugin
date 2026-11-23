@extends('layouts.store')
@section('title', __('Connexion'))
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')
<div class="container section" style="max-width:480px"><div class="card">
  <h1 style="font-size:1.5rem">{{ request()->routeIs('admin.login') ? __('Connexion back-office') : __('Connexion') }}</h1>
  <form method="post" action="{{ route('login') }}">@csrf
    <div class="field"><label for="email">{{ __('E-mail') }}</label><input id="email" type="email" name="email" required autofocus autocomplete="username" value="{{ old('email') }}">@error('email')<div class="error-text">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="password">{{ __('Mot de passe') }}</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('Rester connecté') }}</label>
    <button class="btn btn-accent btn-block" style="margin-top:14px">{{ __('Se connecter') }}</button>
  </form>
  <p class="small" style="margin-top:12px"><a href="{{ route('password.request') }}">{{ __('Mot de passe oublié ?') }}</a> · <a href="{{ route('register') }}">{{ __('Créer un compte') }}</a></p>
</div></div>
@endsection
