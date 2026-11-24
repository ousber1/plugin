@extends('layouts.store')
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')
<div class="container section" style="padding-top:20px">
  <div class="account-layout">
    <nav class="account-nav" aria-label="{{ __('Espace client') }}">
      @foreach(['account.dashboard' => [__('Tableau de bord'), 'grid'], 'account.orders' => [__('Mes commandes'), 'bag'], 'account.quotes' => [__('Mes devis'), 'quote'], 'account.invoices' => [__('Factures'), 'file'], 'account.designs' => [__('Mes créations'), 'palette'], 'account.addresses.index' => [__('Adresses'), 'home'], 'account.profile' => [__('Profil'), 'user']] as $r => [$l, $ic])
        <a href="{{ route($r) }}" @class(['active' => request()->routeIs($r.'*')])>@icon($ic) {{ $l }}</a>
      @endforeach
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm btn-block" style="margin-top:8px">@icon('logout') {{ __('Déconnexion') }}</button></form>
    </nav>
    <div>@yield('account')</div>
  </div>
</div>
@endsection
