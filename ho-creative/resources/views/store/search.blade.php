@extends('layouts.store')
@section('title', $q ? __('Recherche : :q', ['q' => $q]) : __('Tous les produits'))
@push('head')<meta name="robots" content="noindex,follow">@endpush
@section('content')
<div class="container">
  <nav class="breadcrumbs"><a href="{{ route('home') }}">{{ __('Accueil') }}</a> › {{ __('Recherche') }}</nav>
  <h1>{{ $q ? __('Résultats pour « :q »', ['q' => $q]) : __('Tous les produits') }}</h1>
  <div class="cat-layout">
    @include('store._filters')
    <div>
      <div class="products">@forelse($products as $p) @include('partials.product-tile', ['p' => $p]) @empty <div class="card"><p>{{ __('Aucun produit trouvé.') }}</p><a class="btn btn-accent" href="{{ route('quote.request') }}">{{ __('Demander un devis sur mesure') }}</a></div> @endforelse</div>
      {{ $products->links('partials.pagination') }}
    </div>
  </div>
</div>
@endsection
