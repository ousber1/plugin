@php $embed = $c['url'] ? \App\Support\Builder::embed($c['url']) : null; @endphp
@include('builder.blocks._head', ['title' => $t('title')])
@if ($embed)
    <div class="bk-video"><iframe src="{{ $embed }}" title="{{ $t('title') ?: 'video' }}" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
    @if ($t('caption'))<p class="muted small" style="text-align:center;margin-top:8px">{{ $t('caption') }}</p>@endif
@else
    <div class="bk-video bk-video--empty"><x-icon name="play" size="40"/></div>
@endif
