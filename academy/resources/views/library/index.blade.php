@extends('layouts.app')
@section('title', $category ? $category->tr('name').' — '.__('المكتبة') : __('المكتبة'))
@section('description', __('موارد تعليمية قابلة للتحميل: ملخصات، قوالب، تمارين وكتيبات.'))
@section('canonical', route('library.index', $category ? ['category' => $category->slug] : []))
@section('content')
<section class="page-hero">
    <div class="container">
        <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li>{{ __('المكتبة') }}</li></ol>
        <h1>{{ $category ? $category->tr('name') : __('مكتبة الموارد') }}</h1>
        <p>{{ __('ملخصات وقوالب وتمارين قابلة للتحميل. بعض الموارد مخصّصة للطلاب المسجلين في دورة معيّنة.') }}</p>
    </div>
</section>
<div class="container section--tight">
    <form class="filters overlap" method="GET" role="search">
        <div class="field" style="flex:2"><label for="lq">{{ __('بحث') }}</label><input class="input" id="lq" type="search" name="q" value="{{ request('q') }}" dir="auto" placeholder="{{ __('اسم المورد أو موضوعه') }}"></div>
        <div class="field"><label for="lc">{{ __('التصنيف') }}</label>
            <select class="select" id="lc" name="category"><option value="">{{ __('الكل') }}</option>@foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(request('category') === $c->slug)>{{ $c->tr('name') }}</option>@endforeach</select></div>
        <div class="field"><label for="la">{{ __('الوصول') }}</label>
            <select class="select" id="la" name="access"><option value="">{{ __('الكل') }}</option><option value="public" @selected(request('access') === 'public')>{{ __('متاح للجميع') }}</option><option value="enrolled" @selected(request('access') === 'enrolled')>{{ __('للطلاب المسجلين') }}</option></select></div>
        <button class="btn btn--primary" type="submit">{{ __('تطبيق') }}</button>
    </form>
    @if ($resources->isEmpty())
        <div class="card empty"><span class="empty__icon"><x-icon name="folder" size="28"/></span><h2 style="font-size:1.2rem">{{ __('لا توجد موارد مطابقة') }}</h2><a class="btn btn--primary" href="{{ route('library.index') }}">{{ __('كل الموارد') }}</a></div>
    @else
        <div class="grid grid--3">
            @foreach ($resources as $r)
                @php $allowed = ! $r->isRestricted() || ($r->course_id && in_array($r->course_id, $enrolledIds)) || auth()->user()?->isAdmin(); @endphp
                <article class="card card--pad stack">
                    <div class="row row--between">
                        <span class="badge badge--gray">{{ $r->extension() }} · {{ number_format($r->size / 1048576, 1) }} MB</span>
                        @if ($r->isRestricted())<span class="badge badge--amber"><x-icon name="lock" size="12"/> {{ __('للطلاب المسجلين') }}</span>@else<span class="badge badge--green">{{ __('متاح للجميع') }}</span>@endif
                    </div>
                    <h2 style="font-size:1.05rem;margin:0" dir="auto">{{ $r->tr('title') }}</h2>
                    @if ($r->tr('description'))<p class="small muted" style="margin:0" dir="auto">{{ $r->tr('description') }}</p>@endif
                    @if ($r->isRestricted() && $r->course)<p class="small" style="margin:0">{{ __('مرتبط بدورة:') }} <a href="{{ route('courses.show', $r->course) }}" dir="auto">{{ $r->course->tr('title') }}</a></p>@endif
                    @if ($allowed)
                        <a class="btn btn--primary btn--sm" href="{{ route('library.download', $r) }}"><x-icon name="download" size="16"/> {{ __('تحميل') }}</a>
                    @elseif ($r->course)
                        <a class="btn btn--outline btn--sm" href="{{ route('courses.show', $r->course) }}"><x-icon name="lock" size="16"/> {{ __('سجّل في الدورة للتحميل') }}</a>
                    @endif
                </article>
            @endforeach
        </div>
        {{ $resources->links() }}
    @endif
</div>
@endsection
