@extends('layouts.admin')
@section('title', 'مركز')
@section('content')
@php $selected = old('course_ids', $item->exists ? $item->courses()->pluck('courses.id')->all() : []); @endphp
<div class="page-head"><h1>{{ $item->exists ? 'تعديل المركز' : 'مركز جديد' }}</h1>
    @if ($item->exists && $item->is_active)<a class="btn btn--ghost" href="{{ route('centers.show', $item) }}" target="_blank"><x-icon name="eye" size="18"/> عرض</a>@endif</div>
<form class="card card--pad" style="max-width:900px" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.centers.update', $item) : route('admin.centers.store') }}">
    @csrf @if ($item->exists) @method('PUT') @endif
    <div class="form-grid">
        <x-admin.tr name="name" label="اسم المركز" :value="$item->name" required :max="120"/>
        <x-admin.tr name="city" label="المدينة" :value="$item->city" required :max="80"/>
    </div>
    <x-admin.tr name="address" label="العنوان" :value="$item->address"/>
    <x-admin.tr name="hours" label="أوقات العمل" :value="$item->hours" hint="مثال: من الإثنين إلى السبت، 9:00 – 19:00"/>
    <x-admin.tr name="description" label="تقديم المركز" :value="$item->description" type="textarea" rich :rows="5"/>
    <div class="form-grid">
        <div class="field"><label for="phone">الهاتف</label><input class="input ltr" id="phone" name="phone" value="{{ old('phone', $item->phone) }}"></div>
        <div class="field"><label for="whatsapp">واتساب المركز (اختياري)</label><input class="input ltr" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $item->whatsapp) }}" placeholder="212600000000">
            <p class="hint">إن تُرك فارغًا يُستعمل رقم الأكاديمية الرئيسي. طلبات استلام الشهادة من هذا المركز تُرسل إلى هذا الرقم.</p></div>
        <div class="field"><label for="email">البريد</label><input class="input ltr" type="email" id="email" name="email" value="{{ old('email', $item->email) }}"></div>
        <div class="field"><label for="map_url">رابط Google Maps (اختياري)</label><input class="input ltr" type="url" id="map_url" name="map_url" value="{{ old('map_url', $item->map_url) }}" placeholder="https://maps.app.goo.gl/…"></div>
        <div class="field"><label for="latitude">خط العرض (اختياري)</label><input class="input ltr" id="latitude" name="latitude" value="{{ old('latitude', $item->latitude) }}" placeholder="33.5731"></div>
        <div class="field"><label for="longitude">خط الطول (اختياري)</label><input class="input ltr" id="longitude" name="longitude" value="{{ old('longitude', $item->longitude) }}" placeholder="-7.5898"></div>
    </div>
    <p class="hint" style="margin-top:-6px">الخريطة في صفحة المركز تُعرض تلقائيًا من العنوان، أو من الإحداثيات إن أضفتها (أدق).</p>
    <div class="form-grid">
        <div class="field"><label for="image">صورة المركز</label><input class="input" type="file" id="image" name="image" accept="image/png,image/jpeg,image/webp">
            @if ($item->image_path)<img src="{{ $item->imageUrl() }}" alt="" style="max-height:90px;border-radius:10px;margin-top:8px"><label class="check small"><input type="checkbox" name="remove_image" value="1"> حذف الصورة</label>@endif</div>
        <div class="field"><label for="sort_order">الترتيب</label><input class="input" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}"></div>
    </div>
    <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">الدورات المتوفرة حضوريًا في هذا المركز</legend>
        <div class="check-grid">
            @forelse ($courses as $course)
                <label class="check small"><input type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked(in_array($course->id, $selected))> {{ $course->tr('title') }} @if ($course->status !== 'published')<span class="badge badge--gray">مسودة</span>@endif</label>
            @empty<p class="muted small">لا توجد دورات بعد.</p>@endforelse
        </div>
    </fieldset>
    <label class="check field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> مفعّل (ظاهر في الموقع)</label>
    <button class="btn btn--primary btn--lg" type="submit">حفظ المركز</button>
</form>
@endsection
