<!doctype html>
@php($rtl = app()->getLocale() === 'ar')
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'Back-office') · {{ setting('brand.name') }}</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css?v={{ filemtime(public_path('assets/app.css')) }}">
@stack('head')
</head>
<body class="admin">
@php($u = auth()->user())
@php($nav = [
  'Pilotage' => [['admin.dashboard', 'Tableau de bord', 'dashboard.view'], ['admin.reports.index', 'Rapports & exports', 'reports.view']],
  'Ventes' => [['admin.orders.index', 'Commandes', 'orders.view'], ['admin.orders.create', 'Nouvelle commande (comptoir)', 'orders.manage'], ['admin.quote-requests.index', 'Demandes de devis', 'quotes.manage'], ['admin.quotes.index', 'Devis', 'quotes.manage'], ['admin.customers.index', 'Clients', 'customers.manage']],
  'Finance' => [['admin.payments.index', 'Paiements', 'finance.view'], ['admin.documents.index', 'Factures & documents', 'finance.view'], ['admin.r.index:expenses', 'Dépenses', 'expenses.manage']],
  'Atelier' => [['admin.production.board', 'Production (Kanban)', 'production.view'], ['admin.production.list', 'Liste des travaux', 'production.view'], ['admin.deliveries.index', 'Livraisons & retraits', 'delivery.manage'], ['admin.r.index:machines', 'Machines', 'production.manage']],
  'Stock & achats' => [['admin.stock.index', 'Stock', 'stock.manage'], ['admin.r.index:materials', 'Matières', 'stock.manage'], ['admin.po.index', 'Bons de commande', 'purchasing.manage'], ['admin.r.index:suppliers', 'Fournisseurs', 'suppliers.manage']],
  'Catalogue' => [['admin.products.index', 'Produits & prix', 'catalog.view'], ['admin.r.index:categories', 'Catégories', 'catalog.manage'], ['admin.templates.index', 'Modèles', 'templates.manage'], ['admin.r.index:coupons', 'Codes promo', 'catalog.manage'], ['admin.reviews.index', 'Avis', 'reviews.manage']],
  'Contenu' => [['admin.r.index:home', 'Accueil', 'content.manage'], ['admin.r.index:pages', 'Pages', 'content.manage'], ['admin.r.index:menu', 'Navigation', 'content.manage']],
  'Administration' => [['admin.settings.edit', 'Paramètres', 'settings.manage'], ['admin.r.index:zones', 'Zones de livraison', 'settings.manage'], ['admin.r.index:holidays', 'Jours fériés', 'settings.manage'], ['admin.r.index:notifications', 'Notifications', 'settings.manage'], ['admin.staff.index', 'Équipe & rôles', 'staff.manage'], ['admin.audit.index', 'Journal d\'audit', 'audit.view']],
])
<div class="admin-shell">
  <aside class="admin-side" aria-label="Menu back-office">
    <a class="logo" href="{{ route('admin.dashboard') }}">@include('partials.logo')<span>{{ setting('brand.name') }}<small>Back-office</small></span></a>
    @foreach($nav as $group => $links)
      @php($visible = array_filter($links, fn ($l) => $u->can($l[2])))
      @if($visible)
        <h6>{{ $group }}</h6>
        @foreach($visible as $l)
          @php([$r, $param] = array_pad(explode(':', $l[0]), 2, null))
          <a href="{{ $param ? route($r, $param) : route($r) }}" @class(['active' => $param ? request()->is('admin/r/'.$param.'*') : request()->routeIs($r)])>{{ $l[1] }}</a>
        @endforeach
      @endif
    @endforeach
  </aside>
  <div class="admin-main">
    <header class="admin-top no-print">
      <button class="btn btn-sm btn-ghost admin-burger" type="button" aria-label="Menu">☰</button>
      <form action="{{ route('admin.orders.index') }}" class="flex" role="search"><input type="search" name="q" placeholder="N° commande, client, téléphone…" style="width:260px" aria-label="Recherche"></form>
      <div class="flex">
        <a href="{{ route('home') }}" target="_blank" class="small">Voir la boutique ↗</a>
        <span class="small muted">{{ $u->name }} · {{ $u->getRoleNames()->implode(', ') }}</span>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-ghost">Déconnexion</button></form>
      </div>
    </header>
    <main class="admin-content" id="main">
      @include('partials.flash')
      @yield('content')
    </main>
  </div>
</div>
<script src="/assets/app.js?v={{ filemtime(public_path('assets/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
