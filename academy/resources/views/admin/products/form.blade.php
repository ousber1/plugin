@extends('layouts.admin')
@section('title', 'منتج')
@section('content')
<div class="page-head"><h1>{{ $item->exists ? 'تعديل منتج' : 'منتج جديد' }}</h1></div>
<form class="card card--pad" style="max-width:860px" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.products.update', $item) : route('admin.products.store') }}">
    @csrf @if ($item->exists) @method('PUT') @endif
    <x-admin.tr name="title" label="الاسم" :value="$item->title" required/>
    <x-admin.tr name="description" label="الوصف" :value="$item->description" type="textarea" rich :rows="6"/>
    <div class="form-grid">
        <div class="field"><label for="price">السعر (درهم)</label><input class="input ltr" type="number" min="1" step="0.01" id="price" name="price" value="{{ old('price', $item->price_cents ? $item->price_cents / 100 : '') }}" required></div>
        <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="draft" @selected(old('status', $item->status) === 'draft')>مسودة</option><option value="published" @selected(old('status', $item->status) === 'published')>منشور</option></select></div>
    </div>
    <div class="field"><label for="file">الملف الرقمي (خاص) @if ($item->exists)<span class="muted small">— {{ $item->original_name }}</span>@endif</label><input class="input" type="file" id="file" name="file" @unless ($item->exists) required @endunless></div>
    <div class="field"><label for="cover">صورة الغلاف</label><input class="input" type="file" id="cover" name="cover" accept="image/*"></div>
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
@endsection
