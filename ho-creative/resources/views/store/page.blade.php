@extends('layouts.store')
@section('title', $page->t('title'))
@section('description', $page->t('meta_description'))
@section('content')
<div class="container section" style="max-width:860px">
  <h1>{{ $page->t('title') }}</h1>
  <div class="card">{!! \App\Support\Html::clean($page->t('body')) !!}</div>
</div>
@endsection
