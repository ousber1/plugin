@extends('layouts.admin')
@section('title', 'شريك')
@section('content')
<div class="page-head"><h1>{{ $item->exists ? 'تعديل الشريك' : 'شريك جديد' }}</h1></div>
<form class="card card--pad" style="max-width:640px" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.partners.update', $item) : route('admin.partners.store') }}">
    @csrf @if ($item->exists) @method('PUT') @endif
    <div class="field"><label for="name">الاسم (يُستعمل كنص بديل للشعار)</label><input class="input" id="name" name="name" value="{{ old('name', $item->name) }}" required></div>
    <div class="field"><label for="url">الرابط</label><input class="input ltr" type="url" id="url" name="url" value="{{ old('url', $item->url) }}"></div>
    <div class="field"><label for="logo">الشعار</label><input class="input" type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" @unless ($item->exists) required @endunless></div>
    <div class="field"><label for="sort_order">الترتيب</label><input class="input" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}"></div>
    <label class="check field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> مفعّل</label>
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
@endsection
