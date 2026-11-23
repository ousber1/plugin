@extends('account._layout')
@section('title', __('Mon compte'))
@section('account')
<h1>{{ __('Bonjour :name', ['name' => auth()->user()->name]) }}</h1>
<div class="kpis">
  <a class="kpi" href="{{ route('account.orders') }}" style="text-decoration:none;color:inherit"><div class="v">{{ $pendingProofs }}</div><div class="l">{{ __('BAT à valider') }}</div></a>
  <a class="kpi" href="{{ route('account.orders') }}" style="text-decoration:none;color:inherit"><div class="v">{{ $awaitingArtwork }}</div><div class="l">{{ __('Commandes en attente de fichier') }}</div></a>
  <a class="kpi" href="{{ route('account.quotes') }}" style="text-decoration:none;color:inherit"><div class="v">{{ $quotes }}</div><div class="l">{{ __('Devis à consulter') }}</div></a>
</div>
<div class="card">
  <div class="flex between"><h2 style="font-size:1.15rem">{{ __('Commandes récentes') }}</h2><a href="{{ route('account.orders') }}">{{ __('Tout voir') }}</a></div>
  @include('account._orders-table', ['orders' => $orders])
</div>
@endsection
