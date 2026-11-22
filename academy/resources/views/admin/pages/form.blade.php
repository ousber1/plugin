@extends('layouts.admin')
@section('title', 'صفحة')
@section('content')
<div class="page-head"><h1>{{ $page->exists ? $page->tr('title') : 'صفحة جديدة' }}</h1>@if ($page->exists)<a class="btn btn--outline btn--sm" target="_blank" href="{{ route('pages.show', $page) }}">معاينة</a>@endif</div>
<form class="card card--pad" method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
    @csrf @if ($page->exists) @method('PUT') @endif
    <x-admin.tr name="title" label="العنوان" :value="$page->title" required/>
    <x-admin.tr name="body" label="المحتوى" :value="$page->body" type="textarea" rich :rows="14"/>
    <x-admin.tr name="seo_title" label="عنوان SEO" :value="$page->seo_title"/>
    <x-admin.tr name="seo_description" label="وصف SEO" :value="$page->seo_description" type="textarea" :rows="2"/>
    <div class="form-grid">
        @unless (in_array($page->slug, ['about', 'privacy']))
            <div class="field"><label for="slug">الرابط</label><input class="input ltr" id="slug" name="slug" value="{{ old('slug', $page->slug) }}" @unless ($page->exists) required @endunless></div>
            <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="published" @selected(old('status', $page->status) === 'published')>منشور</option><option value="draft" @selected(old('status', $page->status) === 'draft')>مسودة</option></select></div>
        @else<input type="hidden" name="status" value="published">@endunless
    </div>
    <label class="check field"><input type="checkbox" name="show_in_footer" value="1" @checked(old('show_in_footer', $page->show_in_footer))> إظهار في روابط التذييل</label>
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
@endsection
