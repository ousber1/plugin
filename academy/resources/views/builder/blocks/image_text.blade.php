<div class="bk-split bk-split--{{ $c['side'] }}">
    <div class="bk-split__media">@if ($c['image'])<img src="{{ \App\Support\Media::url($c['image']) }}" alt="" loading="lazy">@endif</div>
    <div class="bk-split__text">
        @if ($t('eyebrow'))<span class="eyebrow">{{ $t('eyebrow') }}</span>@endif
        @if ($t('title'))<h2 dir="auto">{{ $t('title') }}</h2>@endif
        <div class="prose" dir="auto">{!! $t('body') !!}</div>
        @if ($t('btn_label'))<a class="btn btn--primary" href="{{ $c['btn_url'] ?: '#' }}">{{ $t('btn_label') }}</a>@endif
    </div>
</div>
