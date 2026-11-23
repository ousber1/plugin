@extends('layouts.app')
@section('title', $page->tr('seo_title') ?: $page->tr('title'))
@section('description', $page->tr('seo_description') ?: \App\Support\Html::excerpt($page->tr('body')))
@section('canonical', route('pages.show', $page))
@section('content')
<section class="page-hero"><div class="container" style="max-width:860px">
    <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li>{{ $page->tr('title') }}</li></ol>
    <h1 dir="auto">{{ $page->tr('title') }}</h1></div></section>
<div class="container" style="max-width:860px">
    <article class="card card--pad overlap prose" dir="auto">{!! $page->tr('body') !!}</article>
</div>
@endsection
