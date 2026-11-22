@extends('layouts.admin')
@section('title', 'تصنيف')
@section('content')
<div class="page-head"><h1>{{ $category->exists ? 'تعديل التصنيف' : 'تصنيف جديد' }} — {{ $types[$category->type] }}</h1></div>
<form class="card card--pad" style="max-width:720px" method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
    @csrf @if ($category->exists) @method('PUT') @else <input type="hidden" name="type" value="{{ $category->type }}"> @endif
    <x-admin.tr name="name" label="الاسم" :value="$category->name" required/>
    <x-admin.tr name="description" label="الوصف" :value="$category->description" type="textarea" :rows="2"/>
    <div class="form-grid">
        <div class="field"><label for="slug">الرابط</label><input class="input ltr" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="تلقائي"></div>
        <div class="field"><label for="icon">الأيقونة</label><select class="select" id="icon" name="icon">@foreach (['layers', 'code', 'briefcase', 'globe', 'chart', 'sparkles', 'award', 'book', 'file-text', 'users', 'lamp', 'store', 'phone', 'shield'] as $ic)<option value="{{ $ic }}" @selected(old('icon', $category->icon) === $ic)>{{ $ic }}</option>@endforeach</select></div>
        <div class="field"><label for="sort_order">الترتيب</label><input class="input" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}"></div>
    </div>
    <label class="check field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))> ظاهر في الموقع</label>
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
@endsection
