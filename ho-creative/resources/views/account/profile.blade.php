@extends('account._layout')
@section('title', __('Profil'))
@section('account')
<h1>{{ __('Mon profil') }}</h1>
<form class="card" method="post" action="{{ route('account.profile.update') }}">@csrf @method('put')
  <div class="grid-2">
    <div class="field"><label for="name">{{ __('Nom') }}</label><input id="name" name="name" required value="{{ old('name', $user->name) }}"></div>
    <div class="field"><label for="phone">{{ __('Téléphone') }}</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}"></div>
    <div class="field"><label>{{ __('E-mail') }}</label><input value="{{ $user->email }}" disabled></div>
    <div class="field"><label for="locale">{{ __('Langue préférée') }}</label><select id="locale" name="locale">@foreach(config('hocreative.locales') as $k => $v)<option value="{{ $k }}" @selected($user->locale === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label for="company_name">{{ __('Société') }}</label><input id="company_name" name="company_name" value="{{ old('company_name', $customer?->company_name) }}"></div>
    <div class="field"><label for="ice">ICE</label><input id="ice" name="ice" value="{{ old('ice', $customer?->ice) }}"></div>
  </div>
  <h3>{{ __('Changer le mot de passe') }}</h3>
  <div class="grid-3">
    <div class="field"><label for="current_password">{{ __('Mot de passe actuel') }}</label><input id="current_password" type="password" name="current_password" autocomplete="current-password"></div>
    <div class="field"><label for="password">{{ __('Nouveau') }}</label><input id="password" type="password" name="password" autocomplete="new-password"></div>
    <div class="field"><label for="password_confirmation">{{ __('Confirmation') }}</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
  </div>
  <button class="btn btn-accent">{{ __('Enregistrer') }}</button>
</form>
@endsection
