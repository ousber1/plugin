{{-- Product card. $p (with sales_count, approved_reviews_avg_rating) --}}
@php
    $compare = $p->comparePrice(); $pct = $p->discountPercent(); $rating = $p->averageRating();
    $sales = $p->sales_count ?? 0;
@endphp
<article class="card pcard">
    <a class="pcard__media" href="{{ route('store.show', $p) }}" tabindex="-1" aria-hidden="true">
        <img src="{{ $p->coverUrl() }}" alt="" loading="lazy" width="640" height="360">
        <span class="pcard__badges">
            @if ($p->isNew())<span class="pbadge pbadge--dark">{{ __('جديد') }}</span>@endif
            @if ($pct)<span class="pbadge pbadge--amber">{{ __('خصم :p%', ['p' => $pct]) }}</span>@endif
        </span>
        @if ($p->isRecentlyUpdated())<span class="pbadge pbadge--green pcard__updated"><x-icon name="check" size="12"/> {{ __('محدث مؤخرًا') }}</span>@endif
        @if ($p->file_format)<span class="pbadge pbadge--ink pcard__format">{{ $p->file_format }}</span>@endif
    </a>
    <div class="pcard__body">
        <div class="pcard__tags">
            @if ($p->category)<a class="ptag" href="{{ route('store.index', ['category' => $p->category->slug]) }}">{{ $p->category->tr('name') }}</a>@endif
            @if ($p->version)<span class="pver ltr">v{{ $p->version }}</span>@endif
        </div>
        <h3 class="pcard__title"><a href="{{ route('store.show', $p) }}" dir="auto">{{ $p->tr('title') }}@if ($p->tr('subtitle')) — {{ $p->tr('subtitle') }}@endif</a></h3>
        <div class="pcard__rating">@include('store._stars', ['rating' => $rating]) <span>{{ $rating ? $rating.' ('.$p->approved_reviews_count.')' : __('كن أول من يقيّم') }}</span></div>
        <div class="pcard__meta">
            @if ($p->file_path)<span><x-icon name="download" size="14"/> {{ __('تحميل فوري') }}</span>@endif
            @if ($sales > 0)<span><x-icon name="users" size="14"/> {{ trans_choice('{1} عملية شراء واحدة|{2} عمليتا شراء|[3,10] :count عمليات شراء|[11,*] :count عملية شراء', $sales, ['count' => $sales]) }}</span>@endif
        </div>
    </div>
    <div class="pcard__foot">
        <div class="pcard__price">
            @if ($compare)<del>{{ \App\Support\Shop::money($compare) }}</del>@endif
            <strong>{{ $p->priceLabel() }}</strong>@if ($p->billingLabel())<small>/ {{ $p->billingLabel() }}</small>@endif
        </div>
        <div class="pcard__actions">
            <form method="POST" action="{{ route('store.cart.add', $p) }}">@csrf
                <button class="icon-btn icon-btn--sm" type="submit" aria-label="{{ __('أضف «:t» إلى السلة', ['t' => $p->tr('title')]) }}" title="{{ __('أضف إلى السلة') }}"><x-icon name="store" size="16"/></button>
            </form>
            <form method="POST" action="{{ route('store.cart.add', $p) }}">@csrf<input type="hidden" name="buy_now" value="1">
                <button class="btn btn--primary btn--sm" type="submit">{{ __('اشترِ الآن') }}</button>
            </form>
        </div>
    </div>
</article>
