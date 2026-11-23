@php $wa = \App\Support\Settings::whatsappLink($t('message') ?: __('السلام عليكم، لدي سؤال حول الأكاديمية.')); @endphp
<div class="bk-wa">
    <span class="bk-wa__icon"><x-icon name="whatsapp" size="34"/></span>
    <div class="bk-wa__text"><h2 dir="auto">{{ $t('title') ?: __('راسلنا عبر واتساب') }}</h2>@if ($t('text'))<p dir="auto">{{ $t('text') }}</p>@endif</div>
    @if ($wa)<a class="btn btn--whatsapp btn--lg" href="{{ $wa }}" target="_blank" rel="noopener"><x-icon name="whatsapp" size="18"/> {{ $t('btn_label') ?: __('راسلنا عبر واتساب') }}</a>@endif
</div>
