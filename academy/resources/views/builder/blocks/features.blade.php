@include('builder.blocks._head', ['title' => $t('title'), 'sub' => $t('subtitle')])
<div class="bk-grid bk-grid--{{ $c['columns'] }}">
    @foreach ($c['items'] as $item)
        <div class="feature"><div class="feature__icon"><x-icon :name="$item['icon']" size="26"/></div><h3 dir="auto">{{ $t('title', $item) }}</h3><p dir="auto">{{ $t('text', $item) }}</p></div>
    @endforeach
</div>
