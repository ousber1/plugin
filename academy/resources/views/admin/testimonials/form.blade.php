@extends('layouts.admin')
@section('title', 'رأي طالب')
@section('content')
<div class="page-head"><h1>{{ $item->exists ? 'تعديل الرأي' : 'إضافة رأي' }}</h1></div>
<form class="card card--pad" style="max-width:720px" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.testimonials.update', $item) : route('admin.testimonials.store') }}">
    @csrf @if ($item->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="name">الاسم</label><input class="input" id="name" name="name" value="{{ old('name', $item->name) }}" required></div>
        <div class="field"><label for="role">الصفة (مثال: طالبة، أكادير)</label><input class="input" id="role" name="role" value="{{ old('role', $item->role) }}"></div>
        <div class="field"><label for="rating">التقييم</label><select class="select" id="rating" name="rating"><option value="">—</option>@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}" @selected(old('rating', $item->rating) == $i)>{{ $i }} ★</option>@endfor</select></div>
        <div class="field"><label for="sort_order">الترتيب</label><input class="input" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}"></div>
    </div>
    <x-admin.tr name="body" label="الرأي" :value="$item->body" type="textarea" required :rows="3"/>
    <div class="field"><label for="photo">صورة (اختياري)</label><input class="input" type="file" id="photo" name="photo" accept="image/*"></div>
    <label class="check field"><input type="checkbox" name="is_approved" value="1" @checked(old('is_approved', $item->is_approved))> معتمد ويظهر في الموقع</label>
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
@endsection
