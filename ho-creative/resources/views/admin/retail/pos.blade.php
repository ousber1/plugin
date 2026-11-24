<!doctype html>
<html lang="fr" dir="ltr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex">
<title>Caisse · {{ $register }} · {{ setting('brand.name') }}</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css?v={{ filemtime(public_path('assets/app.css')) }}">
</head>
<body class="pos-body">
@if(! $session)
  <div class="pos-done" style="background:linear-gradient(135deg,#16193A,#2B2F63)">
    <div class="box" style="text-align:start">
      <a class="logo" href="{{ route('admin.dashboard') }}" style="margin-bottom:14px">@include('partials.logo')<span>{{ setting('brand.name') }}<small>Caisse du magasin</small></span></a>
      @include('partials.flash')
      <h1 style="font-size:1.4rem">Ouvrir la caisse</h1>
      <p class="muted small">Comptez le fond de caisse (espèces présentes dans le tiroir) avant la première vente.</p>
      @can('retail.cash')
      <form method="post" action="{{ route('admin.pos.open') }}" class="stack">@csrf
        <div class="field"><label for="register">Caisse</label><select id="register" name="register">@foreach($registers as $r)<option @selected($r === $register)>{{ $r }}</option>@endforeach</select></div>
        <div class="field"><label for="opening_float">Fond de caisse (DH)</label><input id="opening_float" type="number" step="0.01" min="0" name="opening_float" value="0" required autofocus style="font-size:1.3rem;font-weight:700"></div>
        <button class="btn btn-accent btn-lg btn-block">@icon('register') Ouvrir {{ $register }}</button>
      </form>
      @else
        <div class="alert alert-warn">Aucune caisse ouverte. Demandez à un responsable de l'ouvrir.</div>
      @endcan
      <p class="small" style="margin-top:14px"><a href="{{ route('admin.dashboard') }}">← Retour au back-office</a></p>
    </div>
  </div>
@else
<div class="pos" id="pos"
     data-products="{{ json_encode($products) }}"
     data-sell="{{ route('admin.pos.sell') }}" data-customers="{{ route('admin.pos.customers') }}" data-customer-store="{{ route('admin.pos.customers.store') }}"
     data-register="{{ $register }}" data-can-discount="{{ auth()->user()->can('retail.discount') ? 1 : 0 }}">
  <section class="pos-left">
    <div class="pos-top">
      <a class="logo" href="{{ route('admin.dashboard') }}" title="Back-office">@include('partials.logo')<span class="lbl">{{ setting('brand.name') }}</span></a>
      <div class="pos-scan">@icon('barcode')<input id="scan" type="search" placeholder="Scanner un code-barres ou rechercher un article…  (F2)" autocomplete="off" autofocus aria-label="Recherche article"></div>
      <span class="pill" style="background:rgba(255,255,255,.12);color:#fff">{{ $register }} · ouverte {{ $session->opened_at->format('H:i') }}</span>
      <a href="{{ route('admin.retail.sales.index') }}" class="small">@icon('receipt') Ventes</a>
      @can('retail.cash')<a href="{{ route('admin.retail.sessions.show', $session) }}" class="small">@icon('cash') Clôture</a>@endcan
    </div>
    <nav class="pos-cats" id="cats" aria-label="Rayons">
      <button type="button" class="active" data-cat="">Tous</button>
      @foreach($categories as $c)<button type="button" data-cat="{{ $c->id }}"><i style="background:{{ $c->color }}"></i>{{ $c->name }}</button>@endforeach
    </nav>
    <div class="pos-grid" id="grid" aria-live="polite"></div>
  </section>

  <aside class="pos-right" aria-label="Ticket en cours">
    <div class="pos-cust">
      @icon('user')
      <input id="cust" type="search" placeholder="Client (facultatif) : nom, téléphone, ICE…" autocomplete="off">
      <button type="button" class="btn btn-ghost btn-sm" id="cust-new" title="Nouveau client">@icon('plus')</button>
      <div class="pos-sugg" id="cust-sugg" hidden></div>
    </div>
    <div id="cust-chip" class="small" style="padding:0 16px" hidden></div>
    <div class="pos-lines" id="lines"></div>
    <div class="pos-sum">
      <div class="row"><span>Total HT</span><span id="s-ht">0,00</span></div>
      <div class="row"><span>TVA</span><span id="s-tax">0,00</span></div>
      <div class="row" id="s-disc-row" hidden><span>Remises</span><span id="s-disc">0,00</span></div>
      <div class="grand"><span style="font-size:1rem;color:var(--muted);font-weight:700">Total TTC</span><span id="s-total">0,00 DH</span></div>
    </div>
    <div class="pos-pay">
      <div class="pay-methods" role="radiogroup" aria-label="Moyen de paiement">
        <button type="button" class="active" data-m="cash">@icon('cash')Espèces</button>
        <button type="button" data-m="card_in_store">@icon('card')Carte</button>
        <button type="button" data-m="cheque">@icon('file')Chèque</button>
        <button type="button" data-m="bank_transfer">@icon('wallet')Virement</button>
      </div>
      <div class="flex" style="flex-wrap:nowrap">
        <input id="tender" type="number" step="0.01" min="0" placeholder="Montant reçu" aria-label="Montant reçu" style="font-size:1.15rem;font-weight:700">
        <input id="ref" type="text" placeholder="Réf." aria-label="Référence" style="max-width:110px">
        <button type="button" class="btn btn-ghost" id="add-pay" title="Ajouter ce paiement (paiement mixte)">+</button>
      </div>
      <div class="cash-keys" id="cash-keys"><button type="button" data-k="exact">Exact</button><button type="button" data-k="50">50</button><button type="button" data-k="100">100</button><button type="button" data-k="200">200</button></div>
      <div id="pays" class="small"></div>
      <div class="pos-change" id="change"><span>Rendu monnaie</span><span id="change-v">0,00 DH</span></div>
      <input id="disc-reason" type="text" placeholder="Motif de la remise" hidden>
      <div class="flex" style="flex-wrap:nowrap">
        <button type="button" class="btn btn-ghost" id="clear" title="Vider le ticket">@icon('trash')</button>
        <button type="button" class="btn btn-accent btn-lg grow" id="pay" disabled>@icon('check') Encaisser <span id="pay-total"></span> <span class="tiny" style="opacity:.7">(F9)</span></button>
      </div>
      <p id="pos-err" class="error-text" role="alert"></p>
    </div>
  </aside>
</div>
<div class="pos-done" id="done" hidden>
  <div class="box">
    <div class="ok-ic">@icon('check')</div>
    <h2 style="margin-bottom:4px">Vente enregistrée</h2>
    <p class="muted" id="done-num"></p>
    <div style="font-size:2rem;font-weight:850" id="done-change"></div>
    <p class="muted small">monnaie à rendre</p>
    <div class="flex" style="justify-content:center;margin-top:14px">
      <a class="btn btn-ghost" id="done-ticket" target="_blank">@icon('printer') Imprimer le ticket</a>
      <a class="btn btn-ghost" id="done-sale" target="_blank">@icon('file') Détail / facture A4</a>
      <button class="btn btn-accent" id="done-new" type="button">Nouvelle vente (Entrée)</button>
    </div>
  </div>
</div>
<template id="new-cust-tpl">
  <form class="box" style="text-align:start" id="new-cust-form">
    <h2 style="font-size:1.2rem">Nouveau client</h2>
    <div class="field"><label>Nom *</label><input name="name" required></div>
    <div class="grid-2"><div class="field"><label>Téléphone</label><input name="phone"></div><div class="field"><label>E-mail</label><input name="email" type="email"></div></div>
    <div class="grid-2"><div class="field"><label>Société</label><input name="company_name"></div><div class="field"><label>ICE</label><input name="ice"></div></div>
    <div class="flex"><button class="btn btn-accent">Créer et associer</button><button type="button" class="btn btn-ghost" data-close>Annuler</button></div>
  </form>
</template>
<script src="/assets/pos.js?v={{ filemtime(public_path('assets/pos.js')) }}" defer></script>
@endif
</body>
</html>
