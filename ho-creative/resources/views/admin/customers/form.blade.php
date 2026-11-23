@extends('layouts.admin')
@section('title', 'Client')
@section('content')
<h1>{{ $customer->exists ? 'Modifier '.$customer->displayName() : 'Nouveau client' }}</h1>
<form class="card" method="post" action="{{ $customer->exists ? route('admin.customers.update', $customer) : route('admin.customers.store') }}" style="max-width:900px">@csrf @if($customer->exists) @method('put') @endif
  <input type="hidden" name="return" value="{{ request('return') }}">
  <div class="grid-3">
    <div class="field"><label>Type</label><select name="type"><option value="individual">Particulier</option><option value="company" @selected(old('type', $customer->type) === 'company')>Entreprise</option></select></div>
    <div class="field"><label>Nom du contact *</label><input name="name" required value="{{ old('name', $customer->name) }}"></div>
    <div class="field"><label>Raison sociale</label><input name="company_name" value="{{ old('company_name', $customer->company_name) }}"></div>
    <div class="field"><label>Téléphone</label><input name="phone" value="{{ old('phone', $customer->phone) }}"></div>
    <div class="field"><label>WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $customer->whatsapp) }}"></div>
    <div class="field"><label>E-mail</label><input type="email" name="email" value="{{ old('email', $customer->email) }}"></div>
    <div class="field"><label>ICE</label><input name="ice" value="{{ old('ice', $customer->ice) }}"></div>
    <div class="field"><label>RC</label><input name="rc" value="{{ old('rc', $customer->rc) }}"></div>
    <div class="field"><label>IF</label><input name="if_number" value="{{ old('if_number', $customer->if_number) }}"></div>
    <div class="field"><label>Ville</label><input name="city" value="{{ old('city', $customer->city) }}"></div>
    <div class="field"><label>Plafond de crédit (DH)</label><input type="number" step="0.01" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}"></div>
    <div class="field"><label>Origine</label><select name="source">@foreach(config('hocreative.order_channels') as $ch)<option value="{{ $ch }}" @selected(old('source', $customer->source) === $ch)>{{ __('channel.'.$ch) }}</option>@endforeach</select></div>
  </div>
  <div class="field"><label>Notes</label><textarea name="notes">{{ old('notes', $customer->notes) }}</textarea></div>
  <button class="btn btn-accent">Enregistrer</button>
</form>
@endsection
