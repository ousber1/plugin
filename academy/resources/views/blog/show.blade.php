@extends('layouts.app')
@section('title', $post->tr('seo_title') ?: $post->tr('title'))
@section('description', $post->tr('seo_description') ?: ($post->tr('excerpt') ?: \App\Support\Html::excerpt($post->tr('body'))))
@section('canonical', route('blog.show', $post))
@section('og_type', 'article')
@section('og_image', $post->cover_path ? $post->coverUrl() : asset('images/og-default.png'))
@push('head')
<meta property="article:published_time" content="{{ $post->published_at?->toIso8601String() }}">
@endpush
@section('content')
<section class="page-hero">
    <div class="container" style="max-width:860px">
        <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li><a href="{{ route('blog.index') }}">{{ __('المدونة') }}</a></li>
            @if ($post->category)<li><a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->tr('name') }}</a></li>@endif</ol>
        <h1 dir="auto">{{ $post->tr('title') }}</h1>
        <p class="row small" style="gap:16px">
            @if ($post->author)<span class="row" style="gap:6px"><x-icon name="user" size="16"/> {{ $post->author->name }}</span>@endif
            <span class="row" style="gap:6px"><x-icon name="calendar" size="16"/> <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time></span>
            <span class="row" style="gap:6px"><x-icon name="clock" size="16"/> {{ __(':m د قراءة', ['m' => $post->readingMinutes()]) }}</span>
        </p>
    </div>
</section>
<div class="container" style="max-width:860px">
    <article class="card card--pad overlap">
        <img src="{{ $post->coverUrl() }}" alt="{{ __('صورة مقال :title', ['title' => $post->tr('title')]) }}" style="border-radius:var(--radius);width:100%;margin-bottom:20px" width="640" height="360">
        <div class="prose" dir="auto">{!! $post->tr('body') !!}</div>
        <div class="row" style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px">
            <button class="btn btn--outline btn--sm" type="button" data-share="{{ route('blog.show', $post) }}" data-title="{{ $post->tr('title') }}" data-copied="{{ __('تم نسخ الرابط') }}"><x-icon name="share" size="16"/> {{ __('مشاركة المقال') }}</button>
        </div>
        @if ($post->author && $post->author->bio)
            <div class="row" style="margin-top:20px;background:var(--ivory);padding:16px;border-radius:var(--radius);align-items:flex-start">
                <span class="avatar">{{ $post->author->initials() }}</span>
                <div><strong>{{ $post->author->name }}</strong>@if ($post->author->headline) — <span class="muted small">{{ $post->author->headline }}</span>@endif<p class="small muted" style="margin:4px 0 0">{{ $post->author->bio }}</p></div>
            </div>
        @endif
    </article>
    @if ($related->isNotEmpty())
        <section class="section--tight">
            <h2>{{ __('مقالات ذات صلة') }}</h2>
            <div class="grid grid--3">@foreach ($related as $post) @include('blog.card') @endforeach</div>
        </section>
    @endif
</div>
@endsection
