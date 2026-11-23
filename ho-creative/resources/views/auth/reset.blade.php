@extends('layouts.store')
@section('title', __('Nouveau mot de passe'))
@section('content')
<div class="container section" style="max-width:480px"><div class="card">
  <h1 style="font-size:1.5rem">{{ __('Nouveau mot de passe') }}</h1>
  <form method="post" action="{{ route('password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label for="email">{{ __('E-mail') }}</label><input id="email" type="email" name="email" required value="{{ old('email', $email) }}"></div>
    <div class="field"><label for="password">{{ __('Mot de passe') }}</label><input id="password" type="password" name="password" required autocomplete="new-password"></div>
    <div class="field"><label for="password_confirmation">{{ __('Confirmation') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required></div>
    <button class="btn btn-accent btn-block">{{ __('Enregistrer') }}</button>
  </form>
</div></div>
@endsection
