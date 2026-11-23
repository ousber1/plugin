@extends('layouts.admin')
@section('title', 'مقال')
@section('content')
<div class="page-head"><h1>{{ $post->exists ? 'تعديل مقال' : 'مقال جديد' }}</h1></div>
<form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}">
    @csrf @if ($post->exists) @method('PUT') @endif
    <div class="dash-grid">
        <div class="card card--pad">
            <x-admin.tr name="title" label="العنوان" :value="$post->title" required :max="190"/>
            <x-admin.tr name="excerpt" label="مقتطف" :value="$post->excerpt" type="textarea" :rows="2"/>
            <x-admin.tr name="body" label="المحتوى" :value="$post->body" type="textarea" rich :rows="14"/>
            <x-admin.tr name="seo_title" label="عنوان SEO" :value="$post->seo_title"/>
            <x-admin.tr name="seo_description" label="وصف SEO" :value="$post->seo_description" type="textarea" :rows="2"/>
        </div>
        <div class="stack"><div class="card card--pad">
            <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="draft" @selected(old('status', $post->status) === 'draft')>مسودة</option><option value="published" @selected(old('status', $post->status) === 'published')>منشور</option></select></div>
            <div class="field"><label for="published_at">تاريخ النشر</label><input class="input" type="datetime-local" id="published_at" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"></div>
            <div class="field"><label for="category_id">التصنيف</label><select class="select" id="category_id" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $post->category_id) == $c->id)>{{ $c->tr('name') }}</option>@endforeach</select></div>
            <div class="field"><label for="slug">الرابط</label><input class="input ltr" id="slug" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="تلقائي"></div>
            <div class="field"><label for="cover">صورة الغلاف</label>@if ($post->cover_path)<img src="{{ $post->coverUrl() }}" alt="" style="border-radius:8px;margin-bottom:6px">@endif<input class="input" type="file" id="cover" name="cover" accept="image/*"></div>
            <button class="btn btn--primary btn--block" type="submit">حفظ</button>
        </div></div>
    </div>
</form>
@if ($post->exists)<form method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-confirm="حذف المقال؟" style="margin-top:20px">@csrf @method('DELETE')<button class="btn btn--danger btn--sm">حذف المقال</button></form>@endif
@endsection
