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
  'Pilotage' => [['admin.dashboard', 'Tableau de bord', 'dashboard.view', 'grid'], ['admin.reports.index', 'Rapports & exports', 'reports.view', 'chart']],
  'Magasin' => [['admin.retail.sales.index', 'Ventes magasin', 'retail.view', 'receipt'], ['admin.retail.sessions.index', 'Sessions de caisse', 'retail.cash', 'cash'], ['admin.retail.products.index', 'Articles & stock', 'retail.view', 'tag'], ['admin.r.index:retail_categories', 'Rayons', 'retail.manage', 'layers']],
  'Imprimerie – ventes' => [['admin.orders.index', 'Commandes', 'orders.view', 'bag'], ['admin.orders.create', 'Nouvelle commande', 'orders.manage', 'plus'], ['admin.quote-requests.index', 'Demandes de devis', 'quotes.manage', 'chat'], ['admin.quotes.index', 'Devis', 'quotes.manage', 'quote'], ['admin.customers.index', 'Clients', 'customers.manage', 'users']],
  'Finance' => [['admin.payments.index', 'Paiements', 'finance.view', 'wallet'], ['admin.documents.index', 'Factures & documents', 'finance.view', 'file'], ['admin.r.index:expenses', 'Dépenses', 'expenses.manage', 'card']],
  'Atelier' => [['admin.production.board', 'Production (Kanban)', 'production.view', 'factory'], ['admin.production.list', 'Liste des travaux', 'production.view', 'printer'], ['admin.deliveries.index', 'Livraisons & retraits', 'delivery.manage', 'truck'], ['admin.r.index:machines', 'Machines', 'production.manage', 'settings']],
  'Achats & fournisseurs' => [['admin.suppliers.index', 'Fournisseurs', 'suppliers.manage', 'store'], ['admin.po.index', 'Bons de commande', 'purchasing.manage', 'box'], ['admin.stock.index', 'Matières atelier', 'stock.manage', 'warehouse']],
  'Catalogue en ligne' => [['admin.products.index', 'Produits & prix', 'catalog.view', 'layers'], ['admin.r.index:categories', 'Catégories', 'catalog.manage', 'grid'], ['admin.templates.index', 'Modèles', 'templates.manage', 'palette'], ['admin.r.index:coupons', 'Codes promo', 'catalog.manage', 'percent'], ['admin.reviews.index', 'Avis', 'reviews.manage', 'star']],
  'Contenu' => [['admin.r.index:home', 'Accueil', 'content.manage', 'home'], ['admin.r.index:pages', 'Pages', 'content.manage', 'file'], ['admin.r.index:menu', 'Navigation', 'content.manage', 'menu']],
  'Administration' => [['admin.settings.edit', 'Paramètres', 'settings.manage', 'settings'], ['admin.r.index:zones', 'Zones de livraison', 'settings.manage', 'truck'], ['admin.r.index:holidays', 'Jours fériés', 'settings.manage', 'calendar'], ['admin.r.index:notifications', 'Notifications', 'settings.manage', 'mail'], ['admin.staff.index', 'Équipe & rôles', 'staff.manage', 'shield'], ['admin.audit.index', 'Journal d\'audit', 'audit.view', 'eye']],
])
<div class="admin-shell">
  <aside class="admin-side" aria-label="Menu back-office">
    <a class="logo" href="{{ route('admin.dashboard') }}">@include('partials.logo')<span>{{ setting('brand.name') }}<small>Back-office</small></span></a>
    @can('retail.sell')<a class="pos-link" href="{{ route('admin.pos') }}">@icon('register') Ouvrir la caisse (POS)</a>@endcan
    @foreach($nav as $group => $links)
      @php($visible = array_filter($links, fn ($l) => $u->can($l[2])))
      @if($visible)
        <h6>{{ $group }}</h6>
        @foreach($visible as $l)
          @php([$r, $param] = array_pad(explode(':', $l[0]), 2, null))
          <a href="{{ $param ? route($r, $param) : route($r) }}" @class(['active' => $param ? request()->is('admin/r/'.$param.'*') : (request()->routeIs($r) || (str_ends_with($r, '.index') && request()->routeIs(substr($r, 0, -6).'.*')))])>@icon($l[3]){{ $l[1] }}</a>
        @endforeach
      @endif
    @endforeach
  </aside>
  <div class="admin-main">
    <header class="admin-top no-print">
      <div class="flex" style="flex-wrap:nowrap">
        <button class="btn btn-ghost btn-icon admin-burger" type="button" aria-label="Menu">@icon('menu')</button>
        <form action="{{ route('admin.orders.index') }}" class="search-mini" role="search">@icon('search')<input type="search" name="q" placeholder="Commande, client, téléphone…" aria-label="Recherche"></form>
      </div>
      <div class="admin-user">
        <a href="{{ route('home') }}" target="_blank" class="btn btn-ghost btn-sm">@icon('globe')<span class="lbl">Boutique</span></a>
        <div class="avatar" title="{{ $u->name }}">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</div>
        <div class="small" style="line-height:1.2"><b>{{ $u->name }}</b><br><span class="muted tiny">{{ $u->getRoleNames()->map(fn ($r) => __('role.'.$r))->implode(', ') }}</span></div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-icon" title="Déconnexion" aria-label="Déconnexion">@icon('logout')</button></form>
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
