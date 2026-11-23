@php $list = \App\Support\Settings::feature('blog') ? \App\Models\Post::published()->with('category')->latest('published_at')->limit($c['count'])->get() : collect(); @endphp
@if ($list->isNotEmpty())
    <div class="section__head"><div>@if ($t('title'))<h2 dir="auto">{{ $t('title') }}</h2>@endif</div><a class="btn btn--outline" href="{{ route('blog.index') }}">{{ __('كل المقالات') }}</a></div>
    <div class="grid grid--3">@foreach ($list as $post) @include('blog.card') @endforeach</div>
@endif
