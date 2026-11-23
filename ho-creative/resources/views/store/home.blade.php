@extends('layouts.store')
@section('content')
@foreach($sections as $section)
  @php($c = $section->content ?? [])
  @switch($section->type)
    @case('hero')
      <section class="hero"><div class="container">
        <div>
          <div class="cmyk" aria-hidden="true"><i style="background:#00A0E3"></i><i style="background:#E4007C"></i><i style="background:#FFD400"></i><i style="background:#fff"></i></div>
          <h1>{{ tr($section->title) }}</h1>
          <p>{{ tr($c['text'] ?? '') }}</p>
          <div class="flex" style="margin-top:20px">
            <a class="btn btn-accent" href="{{ $c['cta_url'] ?? '#categories' }}">{{ tr($c['cta_label'] ?? __('Découvrir nos produits')) }}</a>
            <a class="btn btn-ghost" style="color:#fff;border-color:#59608f" href="{{ route('quote.request') }}">{{ __('Demander un devis') }}</a>
          </div>
        </div>
        <div class="hero-art" aria-hidden="true">
          <div class="sheet" style="inset-inline-start:6%;top:8%;width:58%;transform:rotate(-6deg)"><img src="/images/catalog/cartes-de-visite.svg" alt=""></div>
          <div class="sheet" style="inset-inline-end:4%;top:22%;width:50%;transform:rotate(5deg)"><img src="/images/catalog/flyers.svg" alt=""></div>
          <div class="sheet" style="inset-inline-start:24%;bottom:0;width:46%;transform:rotate(-1deg)"><img src="/images/catalog/packaging.svg" alt=""></div>
        </div>
      </div></section>
      @break
    @case('promotions')
      <section class="section"><div class="container">
        @if(tr($section->title))<h2>{{ tr($section->title) }}</h2>@endif
        <div class="promo-strip">
          @foreach($c['items'] ?? [] as $promo)
            <a class="promo" href="{{ $promo['url'] ?? '#' }}"><strong>{{ tr($promo['title'] ?? '') }}</strong><span>{{ tr($promo['text'] ?? '') }}</span></a>
          @endforeach
        </div>
      </div></section>
      @break
    @case('categories')
      <section class="section" id="categories"><div class="container">
        <div class="section-head"><h2>{{ tr($section->title) ?: __('Nos produits') }}</h2></div>
        <div class="tiles">
          @foreach($categories as $cat)
            <a class="tile" href="{{ route('category', $cat->slug) }}">
              <div class="thumb"><img src="{{ $cat->image ?: '/images/placeholder.svg' }}" alt="" loading="lazy" width="400" height="300"></div>
              <div class="body"><div class="title">{{ $cat->t('name') }}</div></div>
            </a>
          @endforeach
        </div>
      </div></section>
      @break
    @case('popular')
      @if($popular->isNotEmpty())
      <section class="section" style="padding-top:0"><div class="container">
        <div class="section-head"><h2>{{ tr($section->title) ?: __('Produits populaires') }}</h2><a href="{{ route('search') }}">{{ __('Tout voir') }} →</a></div>
        <div class="products">@foreach($popular as $p) @include('partials.product-tile', ['p' => $p]) @endforeach</div>
      </div></section>
      @endif
      @break
    @case('how_it_works')
      <section class="section" style="background:#fff;border-block:1px solid var(--line)"><div class="container">
        <h2>{{ tr($section->title) ?: __('Comment ça marche ?') }}</h2>
        <div class="steps" style="margin-top:18px">
          @foreach($c['steps'] ?? [] as $step)
            <div class="step"><h3>{{ tr($step['title'] ?? '') }}</h3><p class="muted" style="margin:0">{{ tr($step['text'] ?? '') }}</p></div>
          @endforeach
        </div>
      </div></section>
      @break
    @case('quote_cta')
      <section class="section"><div class="container"><div class="cta-band">
        <div><h2 style="margin:0 0 6px">{{ tr($section->title) }}</h2><p>{{ tr($c['text'] ?? '') }}</p></div>
        <div class="flex">
          <a class="btn" style="background:#fff;color:var(--ink)" href="{{ route('quote.request') }}">{{ __('Demander un devis') }}</a>
          @if(setting('contact.whatsapp'))<a class="btn btn-wa" href="https://wa.me/{{ setting('contact.whatsapp') }}" rel="noopener">WhatsApp</a>@endif
        </div>
      </div></div></section>
      @break
    @case('reviews')
      @if($reviews->isNotEmpty())
      <section class="section" style="padding-top:0"><div class="container">
        <h2>{{ tr($section->title) ?: __('Avis clients') }}</h2>
        <div class="grid-3">
          @foreach($reviews as $r)
            <div class="review-card">
              <div class="stars" aria-label="{{ $r->rating }}/5">{{ str_repeat('★', $r->rating) }}{{ str_repeat('☆', 5 - $r->rating) }}</div>
              <strong>{{ $r->title }}</strong><p class="small" style="margin:.3em 0">{{ \Illuminate\Support\Str::limit($r->body, 180) }}</p>
              <div class="small muted">{{ $r->author_name }} · <a href="{{ route('product', $r->product->slug) }}">{{ $r->product->t('name') }}</a> @if($r->is_demo)<span class="pill pill-demo">{{ __('Démo') }}</span>@endif</div>
            </div>
          @endforeach
        </div>
      </div></section>
      @endif
      @break
    @case('support')
      <section class="section" style="padding-top:0"><div class="container"><div class="grid-3">
        <div class="card"><h3>📞 {{ __('Par téléphone') }}</h3><p class="muted">{{ setting_t('hours.text') }}</p>@if(setting('contact.phone'))<a class="btn btn-ghost btn-sm" href="tel:{{ setting('contact.phone') }}">{{ setting('contact.phone') }}</a>@endif</div>
        <div class="card"><h3>💬 WhatsApp</h3><p class="muted">{{ __('Envoyez-nous vos fichiers ou vos questions.') }}</p>@if(setting('contact.whatsapp'))<a class="btn btn-wa btn-sm" href="https://wa.me/{{ setting('contact.whatsapp') }}" rel="noopener">{{ __('Écrire sur WhatsApp') }}</a>@endif</div>
        <div class="card"><h3>❓ {{ __('Centre d\'aide') }}</h3><p class="muted">{{ __('Préparer vos fichiers, délais, livraison au Maroc.') }}</p><a class="btn btn-ghost btn-sm" href="{{ setting('contact.help_url', '/page/aide') }}">{{ __('Consulter l\'aide') }}</a></div>
      </div></div></section>
      @break
    @case('rich_text')
      <section class="section"><div class="container card">{!! \App\Support\Html::clean(tr($c['html'] ?? '')) !!}</div></section>
      @break
  @endswitch
@endforeach
@endsection
