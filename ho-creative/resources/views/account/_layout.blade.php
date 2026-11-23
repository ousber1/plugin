@extends('layouts.store')
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')
<div class="container section" style="padding-top:20px">
  <div class="account-layout">
    <nav class="account-nav" aria-label="{{ __('Espace client') }}">
      @foreach(['account.dashboard' => __('Tableau de bord'), 'account.orders' => __('Mes commandes'), 'account.quotes' => __('Mes devis'), 'account.invoices' => __('Factures'), 'account.designs' => __('Mes créations'), 'account.addresses.index' => __('Adresses'), 'account.profile' => __('Profil')] as $r => $l)
        <a href="{{ route($r) }}" @class(['active' => request()->routeIs($r.'*')])>{{ $l }}</a>
      @endforeach
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm" style="margin-top:8px">{{ __('Déconnexion') }}</button></form>
    </nav>
    <div>@yield('account')</div>
  </div>
</div>
@endsection
