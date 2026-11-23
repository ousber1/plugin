<div class="bk-cta bk-cta--{{ $c['look'] }}">
    <div><h2 dir="auto">{{ $t('title') }}</h2>@if ($t('text'))<p dir="auto">{{ $t('text') }}</p>@endif</div>
    @if ($t('btn_label'))<a class="btn {{ $c['look'] === 'band' ? 'btn--white' : 'btn--primary' }} btn--lg" href="{{ $c['btn_url'] ?: '#' }}">{{ $t('btn_label') }}</a>@endif
</div>
