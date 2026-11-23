<div class="bk-buttons">
    @foreach ($c['items'] as $item)
        @continue(! $t('label', $item))
        <a class="btn btn--{{ $item['style'] }} btn--lg" href="{{ $item['url'] ?: '#' }}">@if ($item['style'] === 'whatsapp')<x-icon name="whatsapp" size="18"/> @endif{{ $t('label', $item) }}</a>
    @endforeach
</div>
