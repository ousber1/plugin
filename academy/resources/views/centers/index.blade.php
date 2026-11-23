@extends('layouts.app')
@section('title', __('مراكز التكوين'))
@section('description', __('مراكز الأكاديمية في مدن المغرب: العناوين، أوقات العمل، الدورات الحضورية والتواصل المباشر.'))
@section('content')
<section class="page-hero">
    <div class="container">
        <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li>{{ __('مراكزنا') }}</li></ol>
        <div class="row row--between" style="align-items:end">
            <div>
                <h1>{{ __('مراكز التكوين') }}</h1>
                <p>{{ __('تعلّم عن بُعد أو زرنا في أقرب مركز إليك. يمكنك أيضًا استلام شهادتك الورقية من المركز.') }}</p>
            </div>
            <div class="hero-stat"><strong>{{ $centers->count() }}</strong>{{ __('مركز') }} · <strong>{{ $cities->count() }}</strong>{{ __('مدينة') }}</div>
        </div>
    </div>
</section>
<div class="container section--tight">
    @if ($cities->count() > 1)
        <nav class="chips" aria-label="{{ __('تصفية حسب المدينة') }}" style="margin-bottom:22px">
            <a class="chip {{ $city === '' ? 'is-active' : '' }}" href="{{ route('centers.index') }}" @if ($city === '') aria-current="true" @endif>{{ __('كل المدن') }} <span class="chip__count">{{ $centers->count() }}</span></a>
            @foreach ($cities as $c)
                <a class="chip {{ $city === $c ? 'is-active' : '' }}" href="{{ route('centers.index', ['city' => $c]) }}" @if ($city === $c) aria-current="true" @endif><x-icon name="map-pin" size="14"/> {{ $c }} <span class="chip__count">{{ $centers->filter(fn ($x) => $x->tr('city') === $c)->count() }}</span></a>
            @endforeach
        </nav>
    @endif
    <div class="center-grid">
        @forelse ($shown as $center)
            @include('centers._card', ['center' => $center])
        @empty
            <p class="muted">{{ __('لا توجد مراكز في هذه المدينة بعد.') }}</p>
        @endforelse
    </div>
</div>
@endsection
