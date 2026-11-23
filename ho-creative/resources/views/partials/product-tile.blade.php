<a class="tile" href="{{ route('product', $p->slug) }}">
  <div class="thumb"><img src="{{ $p->image() }}" alt="{{ $p->t('name') }}" loading="lazy" width="400" height="300"></div>
  <div class="body">
    <div class="title">{{ $p->t('name') }}</div>
    @if($p->sale_mode === 'quote')
      <div class="price"><span class="pill pill-accent">{{ __('Sur devis') }}</span></div>
    @elseif($p->from_price !== null)
      <div class="price">{{ __('À partir de') }} <b>{{ money($p->from_price) }}</b> {{ __('HT') }}</div>
    @endif
    @if($p->is_demo)<span class="pill pill-demo">{{ __('Démo') }}</span>@endif
  </div>
</a>
