@extends('layouts.store')
@section('title', $category->t('name'))
@section('description', \Illuminate\Support\Str::limit(strip_tags($category->t('description')), 155))
@section('canonical', route('category', $category->slug))
@section('content')
<div class="container">
  <nav class="breadcrumbs" aria-label="{{ __('Fil d\'Ariane') }}"><a href="{{ route('home') }}">{{ __('Accueil') }}</a> › @if($category->parent)<a href="{{ route('category', $category->parent->slug) }}">{{ $category->parent->t('name') }}</a> › @endif {{ $category->t('name') }}</nav>
  <h1>{{ $category->t('name') }}</h1>
  @if($category->t('description'))<p class="muted" style="max-width:780px">{{ $category->t('description') }}</p>@endif
  @if($children->isNotEmpty())
    <div class="flex" style="margin:10px 0 20px">@foreach($children as $ch)<a class="btn btn-ghost btn-sm" href="{{ route('category', $ch->slug) }}">{{ $ch->t('name') }}</a>@endforeach</div>
  @endif
  <div class="cat-layout">
    @include('store._filters')
    <div>
      <div class="products">@forelse($products as $p) @include('partials.product-tile', ['p' => $p]) @empty <p>{{ __('Aucun produit ne correspond à vos critères.') }}</p> @endforelse</div>
      {{ $products->links('partials.pagination') }}
    </div>
  </div>
</div>
@endsection
