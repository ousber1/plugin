@php $qid = 'bk-q-'.uniqid(); @endphp
<div class="bk-hero bk-hero--{{ $c['height'] }} {{ $c['image'] ? 'has-image' : '' }}">
    <div class="bk-hero__text">
        @if ($t('badge'))<span class="hero__badge"><span class="dot"></span> {{ $t('badge') }}</span>@endif
        @if ($t('title'))<h1 dir="auto">{{ $t('title') }}</h1>@endif
        @if ($t('subtitle'))<p class="bk-hero__lead" dir="auto">{{ $t('subtitle') }}</p>@endif
        @if ($c['show_search'])
            <form class="search-bar" action="{{ route('courses.index') }}" method="GET" role="search">
                <x-icon name="search"/><label for="{{ $qid }}" class="sr-only">{{ __('ابحث عن دورة') }}</label>
                <input id="{{ $qid }}" type="search" name="q" placeholder="{{ __('ابحث عن دورة: برمجة، تصميم، لغة إنجليزية…') }}" dir="auto">
                <button class="btn btn--primary" type="submit">{{ __('ابحث') }}</button>
            </form>
        @endif
        @if ($t('btn1_label') || $t('btn2_label'))
            <div class="hero__actions">
                @if ($t('btn1_label'))<a class="btn btn--amber btn--lg" href="{{ $c['btn1_url'] ?: '#' }}">{{ $t('btn1_label') }}</a>@endif
                @if ($t('btn2_label'))<a class="btn {{ $light ? 'btn--glass' : 'btn--outline' }} btn--lg" href="{{ $c['btn2_url'] ?: '#' }}">{{ $t('btn2_label') }}</a>@endif
            </div>
        @endif
    </div>
    @if ($c['image'])<div class="bk-hero__media"><img src="{{ \App\Support\Media::url($c['image']) }}" alt="" loading="eager"></div>@endif
</div>
