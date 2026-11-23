@extends('layouts.admin')
@section('title', 'Options & prix')
@section('content')
@php($published = $versions->firstWhere('status', 'published'))
<div class="page-head">
  <h1>Options & prix – {{ $product->t('name') }}</h1>
  <div class="flex"><a class="btn btn-sm btn-ghost" href="{{ route('admin.products.edit', $product) }}">← Fiche produit</a><a class="btn btn-sm btn-ghost" href="{{ route('product', $product->slug) }}" target="_blank">Voir ↗</a></div>
</div>
<div class="alert alert-info small">
  Vous éditez le <strong>brouillon v{{ $draft->version }}</strong>. Version en ligne : <strong>{{ $published ? 'v'.$published->version : 'aucune' }}</strong>.
  Testez vos changements dans l'aperçu, puis publiez. Les devis et commandes existants gardent leurs montants et la version utilisée.
  Ordre d'application : base (paliers ou m²) → suppléments m², unité, millier, fixes, calage → minimum de facturation → pourcentages → création graphique. Arrondi : 2 décimales au demi supérieur par composante.
</div>
<div class="split">
  <div class="pricing-editor" id="pricing-editor">
    <form id="pe-form" method="post" action="{{ route('admin.pricing.save', $product) }}">@csrf
      <textarea name="definition" id="pe-json" hidden>{{ json_encode($draft->definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</textarea>
      <div id="pe-ui"></div>
      <fieldset><legend>JSON avancé</legend>
        <p class="small muted">Pour les experts : modifiez directement la définition puis cliquez « Appliquer le JSON ».</p>
        <textarea id="pe-raw" rows="10" class="code"></textarea>
        <button type="button" class="btn btn-sm btn-ghost" id="pe-apply-raw">Appliquer le JSON</button>
      </fieldset>
      <div class="field"><label for="notes">Note de version</label><input id="notes" name="notes" value="{{ $draft->notes }}" placeholder="ex. hausse papier 350 g"></div>
      <div class="flex">
        <button class="btn">💾 Enregistrer le brouillon</button>
        <button class="btn btn-accent" formaction="{{ route('admin.pricing.publish', $product) }}" onclick="return confirm('Publier la version {{ $draft->version }} ? Elle remplacera la grille en ligne.')">🚀 Publier v{{ $draft->version }}</button>
      </div>
    </form>
  </div>
  <div>
    <div class="card" style="position:sticky;top:80px">
      <h2 style="font-size:1.1rem">Aperçu du calcul (brouillon)</h2>
      <div id="pv-errors"></div>
      <div id="pv-inputs" class="stack"></div>
      <div id="pv-result" class="price-box" style="margin-top:12px"></div>
      <p class="small muted" id="pv-live"></p>
    </div>
    <div class="card">
      <h3>Versions</h3>
      <table class="small"><thead><tr><th>Version</th><th>Statut</th><th>Publiée</th><th>Note</th></tr></thead><tbody>
      @foreach($versions as $v)<tr><td>v{{ $v->version }}</td><td><span class="pill {{ $v->status === 'published' ? 'pill-ok' : '' }}">{{ $v->status }}</span></td><td>{{ $v->published_at ? d($v->published_at, true).' – '.$v->publisher?->name : '—' }}</td><td>{{ $v->notes }}</td></tr>@endforeach
      </tbody></table>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>window.PE_PREVIEW_URL = @json(route('admin.pricing.preview', $product));</script>
<script src="/assets/admin-pricing.js?v={{ filemtime(public_path('assets/admin-pricing.js')) }}" defer></script>
@endpush
