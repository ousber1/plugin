@if ($c['image'])
    <figure class="bk-image {{ $c['rounded'] ? 'is-rounded' : '' }} {{ $c['shadow'] ? 'has-shadow' : '' }}">
        @if ($c['link'])<a href="{{ $c['link'] }}">@endif
        <img src="{{ \App\Support\Media::url($c['image']) }}" alt="{{ $t('alt') }}" loading="lazy">
        @if ($c['link'])</a>@endif
        @if ($t('caption'))<figcaption>{{ $t('caption') }}</figcaption>@endif
    </figure>
@endif
