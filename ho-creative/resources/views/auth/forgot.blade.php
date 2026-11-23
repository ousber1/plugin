@extends('layouts.store')
@section('title', __('Mot de passe oublié'))
@section('content')
<div class="container section" style="max-width:480px"><div class="card">
  <h1 style="font-size:1.5rem">{{ __('Mot de passe oublié') }}</h1>
  <form method="post" action="{{ route('password.email') }}">@csrf
    <div class="field"><label for="email">{{ __('E-mail') }}</label><input id="email" type="email" name="email" required value="{{ old('email') }}"></div>
    <button class="btn btn-accent btn-block">{{ __('Envoyer le lien') }}</button>
  </form>
</div></div>
@endsection
