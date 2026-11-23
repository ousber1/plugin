@extends('layouts.admin')
@section('title', 'مورد')
@section('content')
<div class="page-head"><h1>{{ $item->exists ? 'تعديل مورد' : 'مورد جديد' }}</h1></div>
<form class="card card--pad" style="max-width:760px" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.resources.update', $item) : route('admin.resources.store') }}">
    @csrf @if ($item->exists) @method('PUT') @endif
    <x-admin.tr name="title" label="العنوان" :value="$item->title" required/>
    <x-admin.tr name="description" label="الوصف" :value="$item->description" type="textarea" :rows="3"/>
    <div class="form-grid">
        <div class="field"><label for="category_id">التصنيف</label><select class="select" id="category_id" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $item->category_id) == $c->id)>{{ $c->tr('name') }}</option>@endforeach</select></div>
        <div class="field"><label for="access">الوصول</label><select class="select" id="access" name="access"><option value="public" @selected(old('access', $item->access) === 'public')>تحميل عام</option><option value="enrolled" @selected(old('access', $item->access) === 'enrolled')>للطلاب المسجلين في دورة</option></select></div>
        <div class="field"><label for="course_id">الدورة المرتبطة</label><select class="select" id="course_id" name="course_id"><option value="">—</option>@foreach ($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id', $item->course_id) == $c->id)>{{ $c->tr('title') }}</option>@endforeach</select></div>
        <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="draft" @selected(old('status', $item->status) === 'draft')>مسودة</option><option value="published" @selected(old('status', $item->status) === 'published')>منشور</option></select></div>
    </div>
    <div class="field"><label for="file">الملف @if ($item->exists)<span class="muted small">(الحالي: {{ $item->original_name }})</span>@endif</label><input class="input" type="file" id="file" name="file" @unless ($item->exists) required @endunless>
        <p class="hint">يُخزَّن الملف في مساحة خاصة ويُحمَّل عبر التطبيق بعد التحقق من الصلاحية.</p></div>
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
@endsection
