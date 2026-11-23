@php $list = \App\Models\Partner::where('is_active', true)->whereNotNull('logo_path')->orderBy('sort_order')->get(); @endphp
@if ($list->isNotEmpty())
    @include('builder.blocks._head', ['title' => $t('title')])
    <div class="partners">@foreach ($list as $p)<a href="{{ $p->url ?: '#' }}" @if ($p->url) target="_blank" rel="noopener" @endif><img src="{{ \App\Support\Media::url($p->logo_path) }}" alt="{{ $p->name }}" loading="lazy"></a>@endforeach</div>
@elseif ($editing ?? false)
    <p class="muted">لا توجد شعارات شركاء مفعّلة بعد (تظهر هذه الرسالة في المعاينة فقط).</p>
@endif
