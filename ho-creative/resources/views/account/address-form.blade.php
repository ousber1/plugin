@extends('account._layout')
@section('title', __('Adresse'))
@section('account')
<h1>{{ $address->exists ? __('Modifier l\'adresse') : __('Nouvelle adresse') }}</h1>
<form class="card" method="post" action="{{ $address->exists ? route('account.addresses.update', $address) : route('account.addresses.store') }}">@csrf @if($address->exists) @method('put') @endif
  <div class="grid-2">
    <div class="field"><label for="label">{{ __('Libellé') }}</label><input id="label" name="label" value="{{ old('label', $address->label) }}"></div>
    <div class="field"><label for="type">{{ __('Type') }}</label><select id="type" name="type"><option value="shipping">{{ __('Livraison') }}</option><option value="billing" @selected(old('type', $address->type) === 'billing')>{{ __('Facturation') }}</option></select></div>
    <div class="field"><label for="name">{{ __('Nom') }} *</label><input id="name" name="name" required value="{{ old('name', $address->name ?? auth()->user()->name) }}"></div>
    <div class="field"><label for="company">{{ __('Société') }}</label><input id="company" name="company" value="{{ old('company', $address->company) }}"></div>
  </div>
  <div class="field"><label for="line1">{{ __('Adresse') }} *</label><input id="line1" name="line1" required value="{{ old('line1', $address->line1) }}"></div>
  <div class="field"><label for="line2">{{ __('Complément') }}</label><input id="line2" name="line2" value="{{ old('line2', $address->line2) }}"></div>
  <div class="grid-3">
    <div class="field"><label for="city">{{ __('Ville') }} *</label><input id="city" name="city" required value="{{ old('city', $address->city) }}"></div>
    <div class="field"><label for="postal_code">{{ __('Code postal') }}</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $address->postal_code) }}"></div>
    <div class="field"><label for="phone">{{ __('Téléphone') }}</label><input id="phone" name="phone" value="{{ old('phone', $address->phone) }}"></div>
  </div>
  <input type="hidden" name="is_default" value="0"><label class="check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->is_default))> {{ __('Adresse par défaut') }}</label>
  <button class="btn btn-accent" style="margin-top:12px">{{ __('Enregistrer') }}</button>
</form>
@endsection
