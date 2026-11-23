<article class="card post-card">
    <img src="{{ $post->coverUrl() }}" alt="{{ __('صورة مقال :title', ['title' => $post->tr('title')]) }}" loading="lazy" width="640" height="360">
    <div class="post-card__body">
        @if ($post->category)<span class="badge" style="align-self:flex-start">{{ $post->category->tr('name') }}</span>@endif
        <h3><a href="{{ route('blog.show', $post) }}" dir="auto">{{ $post->tr('title') }}</a></h3>
        <p class="muted small" style="margin:0" dir="auto">{{ \Illuminate\Support\Str::limit($post->tr('excerpt') ?: \App\Support\Html::excerpt($post->tr('body')), 140) }}</p>
        <div class="post-card__meta">
            <span class="row" style="gap:4px"><x-icon name="calendar" size="14"/> <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time></span>
            <span class="row" style="gap:4px"><x-icon name="clock" size="14"/> {{ __(':m د قراءة', ['m' => $post->readingMinutes()]) }}</span>
        </div>
    </div>
</article>
