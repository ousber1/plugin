@extends('layouts.store')
@section('title', $product->t('meta_title') ?: $product->t('name'))
@section('description', $product->t('meta_description') ?: \Illuminate\Support\Str::limit($product->t('summary'), 155))
@section('canonical', route('product', $product->slug))
@section('og_image', url($product->image()))
@push('head')
<script type="application/ld+json">{!! json_encode(array_filter([
  '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->t('name'), 'description' => $product->t('summary'),
  'image' => url($product->image()), 'sku' => $product->sku, 'brand' => ['@type' => 'Brand', 'name' => setting('brand.name')],
  'offers' => $product->from_price !== null && $product->sale_mode === 'instant' ? ['@type' => 'AggregateOffer', 'priceCurrency' => 'MAD', 'lowPrice' => $product->from_price, 'availability' => 'https://schema.org/InStock'] : null,
  'aggregateRating' => $rating['count'] > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => $rating['average'], 'reviewCount' => $rating['count']] : null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
@php($ui = $initial?->ui ?? ['options' => []])
@php($modes = $product->artworkModes())
<div class="container">
  <nav class="breadcrumbs" aria-label="{{ __('Fil d\'Ariane') }}">
    <a href="{{ route('home') }}">{{ __('Accueil') }}</a> ›
    @if($product->category->parent)<a href="{{ route('category', $product->category->parent->slug) }}">{{ $product->category->parent->t('name') }}</a> ›@endif
    <a href="{{ route('category', $product->category->slug) }}">{{ $product->category->t('name') }}</a> › {{ $product->t('name') }}
  </nav>

  <div class="product-layout">
    <div>
      <div class="gallery-main"><img src="{{ $product->image() }}" alt="{{ $product->t('name') }}" width="800" height="600"></div>
      @if(count($product->images ?? []) > 1)
        <div class="gallery-thumbs">
          @foreach($product->images as $i => $img)
            <button type="button" data-src="{{ $img }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}" aria-label="{{ __('Image :n', ['n' => $i + 1]) }}"><img src="{{ $img }}" alt="" loading="lazy"></button>
          @endforeach
        </div>
      @endif
      <div style="margin-top:22px">
        <div class="tabs" role="tablist">
          <button role="tab" aria-selected="true" aria-controls="tab-desc" id="t-desc">{{ __('Description') }}</button>
          <button role="tab" aria-selected="false" aria-controls="tab-specs" id="t-specs">{{ __('Caractéristiques') }}</button>
          @if($product->guides)<button role="tab" aria-selected="false" aria-controls="tab-guides">{{ __('Guides d\'impression') }}</button>@endif
          @if($product->faq)<button role="tab" aria-selected="false" aria-controls="tab-faq">{{ __('FAQ') }}</button>@endif
          <button role="tab" aria-selected="false" aria-controls="tab-reviews">{{ __('Avis') }} ({{ $rating['count'] }})</button>
        </div>
        <div class="tabpanel" id="tab-desc" role="tabpanel">{!! \App\Support\Html::clean($product->t('description')) ?: '<p>'.e($product->t('summary')).'</p>' !!}</div>
        <div class="tabpanel" id="tab-specs" role="tabpanel" hidden>
          <table class="spec-table">
            @foreach($product->specs ?? [] as $s)<tr><td>{{ tr($s['label'] ?? '') }}</td><td>{{ tr($s['value'] ?? '') }}</td></tr>@endforeach
            @if($initial)<tr><td>{{ __('Délai de production standard') }}</td><td>{{ trans_choice(':n jour ouvré|:n jours ouvrés', $initial->productionDays, ['n' => $initial->productionDays]) }}</td></tr>@endif
          </table>
          @if($product->finishes)
            <h3 style="margin-top:16px">{{ __('Finitions disponibles') }}</h3>
            <div class="flex">@foreach($product->finishes as $f)<span class="pill">{{ tr($f) }}</span>@endforeach</div>
          @endif
        </div>
        @if($product->guides)
        <div class="tabpanel" id="tab-guides" role="tabpanel" hidden>
          <ul>@foreach($product->guides as $g)<li><a href="{{ $g['path'] }}" download>⬇ {{ tr($g['title'] ?? 'Guide') }}</a></li>@endforeach</ul>
          <p class="small muted">{{ __('Prévoyez 3 mm de fond perdu et une marge de sécurité de 4 mm. Fichiers acceptés : PDF, PNG, JPEG.') }}</p>
        </div>
        @endif
        @if($product->faq)
        <div class="tabpanel" id="tab-faq" role="tabpanel" hidden>
          @foreach($product->faq as $f)<details class="faq"><summary>{{ tr($f['q'] ?? '') }}</summary><p>{{ tr($f['a'] ?? '') }}</p></details>@endforeach
        </div>
        @endif
        <div class="tabpanel" id="tab-reviews" role="tabpanel" hidden>
          @if($rating['count'])
            <p><span class="stars">{{ str_repeat('★', (int) round($rating['average'])) }}</span> <strong>{{ $rating['average'] }}/5</strong> · {{ trans_choice(':n avis vérifié|:n avis vérifiés', $rating['count'], ['n' => $rating['count']]) }}</p>
          @else
            <p class="muted">{{ __('Aucun avis publié pour le moment.') }}</p>
          @endif
          @foreach($reviews as $r)
            <div class="review-card" style="margin-bottom:10px">
              <div class="stars">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</div>
              <strong>{{ $r->title }}</strong>
              <p style="margin:.3em 0">{{ $r->body }}</p>
              <div class="small muted">{{ $r->author_name }} · {{ d($r->created_at) }} @if($r->is_demo)<span class="pill pill-demo">{{ __('Démo') }}</span>@endif</div>
            </div>
          @endforeach
          {{ $reviews->links('partials.pagination') }}
          @if($canReview)
            <form method="post" action="{{ route('review.store', $product) }}" class="card" style="margin-top:12px">@csrf
              <h3>{{ __('Laisser un avis') }}</h3>
              <div class="field"><label for="rv-rating">{{ __('Note') }}</label><select id="rv-rating" name="rating" required>@for($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} ★</option>@endfor</select></div>
              <div class="field"><label for="rv-title">{{ __('Titre') }}</label><input id="rv-title" name="title" maxlength="120"></div>
              <div class="field"><label for="rv-body">{{ __('Votre avis') }}</label><textarea id="rv-body" name="body" required minlength="10"></textarea></div>
              <button class="btn btn-sm">{{ __('Envoyer') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>

    <div class="configurator">
      <div class="card">
        <h1 style="font-size:1.6rem">{{ $product->t('name') }}</h1>
        <p class="muted">{{ $product->t('summary') }}</p>
        @if($rating['count'])<p class="small"><span class="stars">{{ str_repeat('★', (int) round($rating['average'])) }}</span> {{ $rating['average'] }}/5 ({{ $rating['count'] }})</p>@endif

        @if($product->sale_mode === 'instant' && $initial)
        <form id="configurator" method="post" action="{{ route('cart.add') }}" enctype="multipart/form-data" data-upload
              data-price-url="{{ route('price', $product) }}"
              data-i18n="{{ json_encode(['total' => __('Total HT'), 'unit' => __('Prix unitaire'), 'pcs' => __('ex.'), 'days' => __('Production : :n jour(s) ouvré(s)'), 'invalid' => __('Configuration impossible'), 'network' => __('Impossible de calculer le prix. Réessayez.')]) }}">
          @csrf
          <input type="hidden" name="product_id" value="{{ $product->id }}">

          @if(isset($ui['size']))
            <div class="opt">
              <label class="opt-label" for="size">{{ __('Format') }}</label>
              <select id="size" name="size">
                @foreach($ui['size']['presets'] as $s)<option value="{{ $s['code'] }}" @selected($ui['size']['value'] === $s['code'])>{{ $s['label'] }}</option>@endforeach
                @if($ui['size']['custom'])<option value="custom" @selected($ui['size']['value'] === 'custom')>{{ __('Dimensions personnalisées') }}</option>@endif
              </select>
              @if($ui['size']['custom'])
                @php($lim = $ui['size']['limits'])
                <div id="custom-size" class="grid-3" style="margin-top:8px" @if($ui['size']['value'] !== 'custom') hidden @endif>
                  <div><label for="cw" class="small">{{ __('Largeur') }}</label><input id="cw" type="number" name="width" step="0.1" min="0" value="{{ $ui['size']['width'] }}"></div>
                  <div><label for="ch" class="small">{{ __('Hauteur') }}</label><input id="ch" type="number" name="height" step="0.1" min="0" value="{{ $ui['size']['height'] }}"></div>
                  <div><label for="cu" class="small">{{ __('Unité') }}</label><select id="cu" name="unit"><option value="mm">mm</option><option value="cm" @selected(($ui['size']['unit'] ?? 'mm') === 'cm')>cm</option></select></div>
                  <p class="help" style="grid-column:1/-1">{{ __('Min :minw × :minh mm, max :maxw × :maxh mm. Arrondi au :r mm supérieur.', ['minw' => $lim['min_w'] ?? 1, 'minh' => $lim['min_h'] ?? 1, 'maxw' => $lim['max_w'] ?? '∞', 'maxh' => $lim['max_h'] ?? '∞', 'r' => $lim['rounding_mm'] ?? 1]) }}</p>
                </div>
              @endif
            </div>
          @endif

          <div class="opt">
            <label class="opt-label" for="qty-select">{{ __('Quantité') }}</label>
            @if($ui['quantity']['presets'])
              <select id="qty-select" name="quantity">
                @foreach($ui['quantity']['presets'] as $q)<option value="{{ $q }}" @selected($ui['quantity']['value'] == $q)>{{ $q }}</option>@endforeach
                @if($ui['quantity']['custom'])<option value="custom">{{ __('Autre quantité…') }}</option>@endif
              </select>
              @if($ui['quantity']['custom'])
                <input id="qty-custom" type="number" min="{{ $ui['quantity']['min'] }}" max="{{ $ui['quantity']['max'] }}" step="{{ $ui['quantity']['step'] }}" hidden disabled style="margin-top:6px" aria-label="{{ __('Quantité personnalisée') }}">
              @endif
            @else
              <input id="qty-select" type="number" name="quantity" min="{{ $ui['quantity']['min'] }}" max="{{ $ui['quantity']['max'] }}" step="{{ $ui['quantity']['step'] }}" value="{{ $ui['quantity']['value'] }}">
            @endif
          </div>

          @foreach($ui['options'] as $o)
            <fieldset class="opt" data-option="{{ $o['code'] }}" @if(! $o['visible']) hidden @endif style="border:0;padding:0;margin:0 0 16px">
              <legend class="opt-label">{{ $o['label'] }}</legend>
              @if($o['help'])<p class="help" style="margin-top:-4px">{{ $o['help'] }}</p>@endif
              @if($o['display'] === 'select')
                <select name="options[{{ $o['code'] }}]" aria-label="{{ $o['label'] }}" @disabled(! $o['visible'])>
                  @foreach($o['choices'] as $c)<option value="{{ $c['code'] }}" data-choice="{{ $c['code'] }}" @selected($o['value'] === $c['code']) @disabled(! $c['available']) @if(! $c['available']) hidden @endif>{{ $c['label'] }}</option>@endforeach
                </select>
              @else
                <div class="choices">
                  @foreach($o['choices'] as $c)
                    <label class="choice @if(! $c['available']) unavailable @endif" data-choice="{{ $c['code'] }}">
                      <input type="radio" name="options[{{ $o['code'] }}]" value="{{ $c['code'] }}" @checked($o['value'] === $c['code']) @disabled(! $c['available'] || ! $o['visible'])>
                      <span>@if($c['image'])<img src="{{ $c['image'] }}" alt="" loading="lazy">@endif{{ $c['label'] }}@if($c['hint'])<small class="muted">{{ $c['hint'] }}</small>@endif</span>
                    </label>
                  @endforeach
                </div>
              @endif
            </fieldset>
          @endforeach

          @if($modes)
          <fieldset class="opt" style="border:0;padding:0;margin:0 0 16px">
            <legend class="opt-label">{{ __('Votre fichier / design') }}</legend>
            <div class="artwork-modes">
              @php($labels = ['upload' => __('J\'envoie mon fichier prêt à imprimer'), 'later' => __('Je commande et j\'enverrai mon fichier plus tard'), 'template' => __('Je personnalise un modèle HO CREATIVE'), 'editor' => __('Je crée mon design en ligne'), 'design_service' => __('HO CREATIVE crée ou modifie mon design')])
              @foreach($modes as $i => $m)
                <label class="choice"><input type="radio" name="artwork_mode" value="{{ $m }}" @checked($i === 0)>
                  <span>{{ $labels[$m] }} @if($m === 'design_service' && $product->design_fee > 0)<span class="pill pill-accent">+ {{ money($product->design_fee) }}</span>@endif</span></label>
              @endforeach
            </div>
            <div data-mode-panel="upload" hidden style="margin-top:10px">
              <div class="dropzone">
                <label for="art-front">{{ __('Fichier recto (PDF, PNG, JPEG – max :mb Mo)', ['mb' => setting('uploads.max_mb')]) }}</label>
                <input id="art-front" type="file" name="artwork_front" accept=".pdf,.png,.jpg,.jpeg">
                <div id="back-upload" hidden style="margin-top:10px"><label for="art-back">{{ __('Fichier verso') }}</label><input id="art-back" type="file" name="artwork_back" accept=".pdf,.png,.jpg,.jpeg"></div>
                <div class="progress" aria-hidden="true"><i></i></div>
              </div>
              <p class="help">{{ __('Vos fichiers restent privés. Un contrôle automatique indicatif est effectué, puis notre prépresse vérifie chaque fichier.') }}</p>
            </div>
            <div data-mode-panel="later" hidden><p class="help">{{ __('Vous pourrez déposer votre fichier depuis votre espace client ou le lien de suivi. La production démarre à réception.') }}</p></div>
            <div data-mode-panel="template" hidden style="margin-top:10px">
              @if($product->templates->isNotEmpty())
                <div class="choices">
                  @foreach($product->templates as $t)
                    <label class="choice"><input type="radio" name="template_id" value="{{ $t->id }}" @checked($loop->first)><span><img src="{{ $t->thumbnail ?: '/images/placeholder.svg' }}" alt="">{{ $t->t('name') }}</span></label>
                  @endforeach
                </div>
                <p class="help">{{ __('Après l\'ajout au panier, l\'éditeur s\'ouvre pour personnaliser le modèle.') }}</p>
              @else
                <p class="help">{{ __('Aucun modèle n\'est encore disponible pour ce produit.') }}</p>
              @endif
            </div>
            <div data-mode-panel="editor" hidden><p class="help">{{ __('Après l\'ajout au panier, l\'éditeur en ligne s\'ouvre au format choisi avec repères de coupe, fond perdu et zone de sécurité.') }}</p></div>
            <div data-mode-panel="design_service" hidden style="margin-top:8px">
              <label for="brief">{{ __('Décrivez votre besoin (textes, couleurs, logo…)') }}</label>
              <textarea id="brief" name="notes" maxlength="3000"></textarea>
              <label for="ref" class="small" style="margin-top:6px">{{ __('Logo ou éléments (optionnel)') }}</label>
              <input id="ref" type="file" name="reference" accept=".pdf,.png,.jpg,.jpeg">
            </div>
          </fieldset>
          @endif

          <div id="price-box" class="price-box" aria-live="polite">
            @if($initial->valid)
              <div class="small">{{ __('Total HT') }}</div><div class="total">{{ money($initial->total) }}</div>
            @endif
          </div>
          <p class="upload-msg small" role="alert"></p>
          <button class="btn btn-accent btn-block" type="submit" data-needs-valid style="margin-top:12px">🛒 {{ __('Ajouter au panier') }}</button>
          <p class="small muted center" style="margin-top:8px">{{ __('Prix HT calculé en temps réel et vérifié par nos serveurs. TVA et livraison calculées au panier.') }}</p>
        </form>
        @else
          <div class="alert alert-info">{{ __('Ce produit est réalisé sur devis : chaque projet est chiffré selon vos dimensions, matériaux et quantités.') }}</div>
          <a class="btn btn-accent btn-block" href="{{ route('quote.request', ['produit' => $product->slug]) }}">{{ __('Demander un devis gratuit') }}</a>
        @endif
        <div class="flex" style="margin-top:12px;justify-content:center">
          @if(setting('contact.whatsapp'))<a class="btn btn-wa btn-sm" rel="noopener" target="_blank" href="https://wa.me/{{ setting('contact.whatsapp') }}?text={{ urlencode(__('Bonjour, je souhaite des informations sur : ').$product->t('name')) }}">WhatsApp</a>@endif
          @if(setting('contact.phone'))<a class="btn btn-ghost btn-sm" href="tel:{{ setting('contact.phone') }}">☎ {{ __('Appeler') }}</a>@endif
          <a class="btn btn-ghost btn-sm" href="{{ route('quote.request', ['produit' => $product->slug]) }}">{{ __('Besoin sur mesure ?') }}</a>
        </div>
      </div>
    </div>
  </div>

  @if($product->templates->isNotEmpty())
    <section class="section"><div class="section-head"><h2>{{ __('Modèles personnalisables') }}</h2><a href="{{ route('templates', ['produit' => $product->slug]) }}">{{ __('Tous les modèles') }} →</a></div>
      <div class="tiles">@foreach($product->templates->take(6) as $t)<div class="tile"><div class="thumb"><img src="{{ $t->thumbnail ?: '/images/placeholder.svg' }}" alt="" loading="lazy"></div><div class="body"><div class="title">{{ $t->t('name') }}</div>@if($t->is_demo)<span class="pill pill-demo">{{ __('Démo') }}</span>@endif</div></div>@endforeach</div>
    </section>
  @endif

  @if($related->isNotEmpty())
    <section class="section" style="padding-top:0"><h2>{{ __('Vous aimerez aussi') }}</h2>
      <div class="products">@foreach($related as $p) @include('partials.product-tile', ['p' => $p]) @endforeach</div>
    </section>
  @endif
</div>
@endsection
