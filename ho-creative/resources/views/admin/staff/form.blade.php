@extends('layouts.admin')
@section('title', 'Équipe')
@section('content')
<h1>{{ $user->exists ? $user->name : 'Nouveau membre' }}</h1>
<form class="card" method="post" action="{{ $user->exists ? route('admin.staff.update', $user) : route('admin.staff.store') }}" style="max-width:700px">@csrf @if($user->exists) @method('put') @endif
  <div class="grid-2"><div class="field"><label>Nom</label><input name="name" required value="{{ old('name', $user->name) }}"></div><div class="field"><label>E-mail</label><input type="email" name="email" required value="{{ old('email', $user->email) }}"></div>
    <div class="field"><label>Téléphone</label><input name="phone" value="{{ old('phone', $user->phone) }}"></div><div class="field"><label>Mot de passe {{ $user->exists ? '(laisser vide = inchangé)' : '' }}</label><input type="password" name="password" autocomplete="new-password" @if(! $user->exists) required @endif><div class="help">10 caractères min., lettres et chiffres.</div></div></div>
  <fieldset style="border:0;padding:0"><legend style="font-weight:700">Rôles</legend>@foreach($roles as $r)<label class="check"><input type="checkbox" name="roles[]" value="{{ $r->name }}" @checked($user->exists && $user->hasRole($r->name))> {{ __('role.'.$r->name) }}</label>@endforeach</fieldset>
  @if($user->exists)<input type="hidden" name="is_active" value="0"><label class="check"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Compte actif</label>@endif
  <button class="btn btn-accent" style="margin-top:10px">Enregistrer</button>
</form>
@endsection
