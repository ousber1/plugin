@extends('account._layout')
@section('title', __('Adresses'))
@section('account')
<div class="flex between"><h1>{{ __('Mes adresses') }}</h1><a class="btn btn-sm" href="{{ route('account.addresses.create') }}">+ {{ __('Ajouter') }}</a></div>
<div class="grid-2">
@forelse($addresses as $a)
  <div class="card">
    <strong>{{ $a->label ?: ($a->type === 'billing' ? __('Facturation') : __('Livraison')) }}</strong> @if($a->is_default)<span class="pill pill-ok">{{ __('Par défaut') }}</span>@endif
    <p class="small">{{ $a->name }} {{ $a->company }}<br>{{ $a->line1 }} {{ $a->line2 }}<br>{{ $a->postal_code }} {{ $a->city }}<br>{{ $a->phone }}</p>
    <div class="flex"><a class="btn btn-sm btn-ghost" href="{{ route('account.addresses.edit', $a) }}">{{ __('Modifier') }}</a>
      <form method="post" action="{{ route('account.addresses.destroy', $a) }}" data-confirm="{{ __('Supprimer cette adresse ?') }}">@csrf @method('delete')<button class="btn btn-sm btn-ghost">{{ __('Supprimer') }}</button></form></div>
  </div>
@empty
  <p class="muted">{{ __('Aucune adresse enregistrée.') }}</p>
@endforelse
</div>
@endsection
