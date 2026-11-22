@extends('layouts.app')
@section('title', $category ? $category->tr('name') : __('المدونة'))
@section('description', $category?->tr('description') ?: __('مقالات عملية في التعلم، البرمجة، التصميم والعمل عبر الإنترنت.'))
@section('canonical', $category ? route('blog.category', $category->slug) : route('blog.index'))
@section('content')
<section class="page-hero page-hero--center">
    <div class="container">
        <span class="eyebrow" style="background:rgba(255,255,255,.12);color:#fff">{{ __('المدونة') }}</span>
        <h1>{{ $category ? $category->tr('name') : __('المدونة') }}</h1>
        <p>{{ $category?->tr('description') ?: __('مقالات عملية في التعلم، البرمجة، التصميم والعمل عبر الإنترنت.') }}</p>
        <form class="search-bar" style="margin:22px auto 0" method="GET" role="search">
            <x-icon name="search"/><label for="bq" class="sr-only">{{ __('ابحث في المقالات') }}</label>
            <input id="bq" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ابحث عن مقال أو موضوع…') }}" dir="auto">
            <button class="btn btn--primary" type="submit">{{ __('ابحث') }}</button>
        </form>
    </div>
</section>
<div class="container section--tight">
    <nav class="chips" aria-label="{{ __('تصنيفات المدونة') }}">
        <a class="chip {{ !$category ? 'is-active' : '' }}" href="{{ route('blog.index') }}">{{ __('الكل') }}</a>
        @foreach ($categories->where('posts_count', '>', 0) as $cat)
            <a class="chip {{ $category?->id === $cat->id ? 'is-active' : '' }}" href="{{ route('blog.category', $cat->slug) }}">{{ $cat->tr('name') }} <span class="chip__count">{{ $cat->posts_count }}</span></a>
        @endforeach
    </nav>
    @if ($posts->isEmpty())
        <div class="card empty"><span class="empty__icon"><x-icon name="file-text" size="28"/></span><h2 style="font-size:1.2rem">{{ __('لا توجد مقالات مطابقة') }}</h2><a class="btn btn--primary" href="{{ route('blog.index') }}">{{ __('كل المقالات') }}</a></div>
    @else
        <div class="grid grid--3">@foreach ($posts as $post) @include('blog.card') @endforeach</div>
        {{ $posts->links() }}
    @endif
</div>
@endsection
