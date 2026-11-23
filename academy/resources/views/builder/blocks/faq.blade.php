@include('builder.blocks._head', ['title' => $t('title')])
<div class="bk-faq">
    @foreach ($c['items'] as $item)
        @continue(! $t('q', $item))
        <details><summary dir="auto">{{ $t('q', $item) }} <x-icon name="chevron-down" size="18"/></summary><p dir="auto">{!! nl2br(e($t('a', $item))) !!}</p></details>
    @endforeach
</div>
