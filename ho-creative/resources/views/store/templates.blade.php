@extends('layouts.store')
@section('title', __('Modèles personnalisables'))
@section('content')
<div class="container section" style="padding-top:20px">
  <h1>{{ __('Modèles personnalisables') }}</h1>
  <p class="muted">{{ __('Choisissez un modèle original HO CREATIVE et personnalisez-le en ligne, puis commandez.') }}</p>
  <div class="tiles">
    @forelse($templates as $t)
      <div class="tile">
        <div class="thumb"><img src="{{ $t->thumbnail ?: '/images/placeholder.svg' }}" alt="" loading="lazy"></div>
        <div class="body"><div class="title">{{ $t->t('name') }}</div>
          <div class="small muted">{{ (float) $t->width_mm }} × {{ (float) $t->height_mm }} mm @if($t->is_demo)<span class="pill pill-demo">{{ __('Démo') }}</span>@endif</div>
          @if($t->product)<a class="btn btn-sm" style="margin-top:8px" href="{{ route('product', $t->product->slug) }}">{{ __('Utiliser ce modèle') }}</a>@endif
        </div>
      </div>
    @empty
      <p>{{ __('Aucun modèle disponible.') }}</p>
    @endforelse
  </div>
  {{ $templates->links('partials.pagination') }}
</div>
@endsection
