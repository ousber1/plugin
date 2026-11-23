@include('builder.blocks._head', ['title' => $t('title')])
<div class="bk-gallery bk-grid bk-grid--{{ $c['columns'] }}">
    @foreach ($c['items'] as $item)
        @continue(! $item['image'])
        <a href="{{ \App\Support\Media::url($item['image']) }}" target="_blank" rel="noopener"><img src="{{ \App\Support\Media::url($item['image']) }}" alt="{{ $t('alt', $item) }}" loading="lazy"></a>
    @endforeach
</div>
