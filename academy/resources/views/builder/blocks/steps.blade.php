@include('builder.blocks._head', ['title' => $t('title')])
<ol class="steps" style="list-style:none;padding:0;margin:0">
    @foreach ($c['items'] as $item)<li class="step"><h3 dir="auto">{{ $t('title', $item) }}</h3><p dir="auto">{{ $t('text', $item) }}</p></li>@endforeach
</ol>
