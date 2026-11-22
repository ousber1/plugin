@extends('layouts.admin')
@section('title', $course->exists ? 'تعديل دورة' : 'دورة جديدة')
@section('content')
<div class="page-head">
    <h1 dir="auto">{{ $course->exists ? $course->tr('title') : 'دورة جديدة' }}</h1>
    @if ($course->exists)
        <div class="row">
            <x-admin.status :status="$course->status"/>
            @if ($course->is_demo)<span class="badge demo-tag">محتوى تجريبي</span>@endif
            <form method="POST" action="{{ route('admin.courses.publish', $course) }}">@csrf
                <button class="btn {{ $course->status === 'published' ? 'btn--outline' : 'btn--amber' }}" type="submit">{{ $course->status === 'published' ? 'إلغاء النشر' : 'نشر الدورة' }}</button></form>
        </div>
    @endif
</div>
@if ($course->exists) @include('admin.courses._nav') @endif
<form method="POST" action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}" enctype="multipart/form-data">
    @csrf @if ($course->exists) @method('PUT') @endif
    <div class="dash-grid">
        <div>
            <div class="card card--pad">
                <x-admin.tr name="title" label="عنوان الدورة" :value="$course->title" required :max="190"/>
                <x-admin.tr name="subtitle" label="وصف مختصر" :value="$course->subtitle" :max="300"/>
                <x-admin.tr name="description" label="عن الدورة" :value="$course->description" type="textarea" rich :rows="8"/>
                <x-admin.tr name="outcomes" label="ماذا سوف يتعلم الطالب؟" :value="$course->outcomes" type="list"/>
                <x-admin.tr name="prerequisites" label="المتطلبات" :value="$course->prerequisites" type="list"/>
                <x-admin.tr name="audience" label="الجمهور المستهدف" :value="$course->audience" type="list"/>
            </div>
            <details class="card card--pad" style="margin-top:16px"><summary style="font-weight:700;cursor:pointer">تحسين محركات البحث (SEO)</summary>
                <div style="margin-top:12px">
                    <x-admin.tr name="seo_title" label="عنوان SEO" :value="$course->seo_title" :max="190" hint="يُستعمل عنوان الدورة إن تُرك فارغًا."/>
                    <x-admin.tr name="seo_description" label="وصف SEO" :value="$course->seo_description" type="textarea" :rows="2"/>
                </div>
            </details>
        </div>
        <div class="stack">
            <div class="card card--pad">
                <div class="field"><label for="slug">الرابط (slug)</label><input class="input ltr" id="slug" name="slug" value="{{ old('slug', $course->slug) }}" placeholder="يُولَّد تلقائيًا"></div>
                <div class="field"><label for="category_id">التصنيف</label><select class="select" id="category_id" name="category_id"><option value="">بدون تصنيف</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $course->category_id) == $c->id)>{{ $c->tr('name') }}</option>@endforeach</select></div>
                <div class="field"><label for="level">المستوى</label><select class="select" id="level" name="level">@foreach (\App\Enums\Level::cases() as $lv)<option value="{{ $lv->value }}" @selected(old('level', $course->level?->value) === $lv->value)>{{ $lv->label() }}</option>@endforeach</select></div>
                <div class="field"><label for="language">لغة التدريس</label><select class="select" id="language" name="language">@foreach (\App\Support\Locales::all() as $code => $m)<option value="{{ $code }}" @selected(old('language', $course->language) === $code)>{{ $m['name'] }}</option>@endforeach</select></div>
                <label class="check field"><input type="checkbox" name="requires_quiz_pass" value="1" @checked(old('requires_quiz_pass', $course->requires_quiz_pass))> يتطلب النجاح في جميع الاختبارات لطلب الشهادة</label>
                <p class="hint">التقدّم يُحسب من الدروس المنشورة المطلوبة. فعّل هذا الخيار لاشتراط النجاح في الاختبارات أيضًا.</p>
            </div>
            <div class="card card--pad">
                <div class="label">صورة الدورة</div>
                @if ($course->exists)<img src="{{ $course->thumbnailUrl() }}" alt="الصورة الحالية للدورة" style="border-radius:12px;margin-bottom:8px;aspect-ratio:16/9;object-fit:cover;width:100%">@endif
                <input class="input" type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" aria-label="رفع صورة">
                <p class="hint">JPG/PNG/WebP بنسبة 16:9. بدون صورة تُولَّد صورة أصلية تلقائيًا.</p>
                @if ($course->thumbnail_path)<label class="check small"><input type="checkbox" name="remove_thumbnail" value="1"> إزالة الصورة المرفوعة</label>@endif
            </div>
            @if (auth()->user()->isAdmin())
                <div class="card card--pad">
                    <div class="field"><label for="instructor_id">المدرّس الظاهر</label><select class="select" id="instructor_id" name="instructor_id"><option value="">—</option>@foreach ($instructors as $i)<option value="{{ $i->id }}" @selected(old('instructor_id', $course->instructor_id) == $i->id)>{{ $i->name }} ({{ $i->role->label() }})</option>@endforeach</select></div>
                    <fieldset class="field" style="border:0;padding:0"><legend class="label">مدرّسون مخوّلون بإدارة الدورة</legend>
                        @php $assigned = old('assignees', $course->exists ? $course->assignees->pluck('id')->all() : []); @endphp
                        @forelse ($instructors->where('role', \App\Enums\Role::Instructor) as $i)
                            <label class="check small"><input type="checkbox" name="assignees[]" value="{{ $i->id }}" @checked(in_array($i->id, $assigned))> {{ $i->name }}</label>
                        @empty<p class="hint">لا يوجد مستخدمون بدور «مدرّس». غيّر الدور من صفحة المستخدمين.</p>@endforelse
                    </fieldset>
                    <label class="check field"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $course->is_featured))> دورة مميزة في الرئيسية</label>
                    <div class="field"><label for="featured_order">ترتيب الظهور</label><input class="input" type="number" min="0" id="featured_order" name="featured_order" value="{{ old('featured_order', $course->featured_order ?? 0) }}"></div>
                </div>
            @endif
            <button class="btn btn--primary btn--lg btn--block" type="submit">حفظ</button>
        </div>
    </div>
</form>
@if ($course->exists && auth()->user()->isAdmin())
    <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" data-confirm="حذف الدورة نهائيًا مع كل دروسها وملفاتها؟" style="margin-top:30px">@csrf @method('DELETE')
        <button class="btn btn--danger btn--sm" type="submit"><x-icon name="trash" size="16"/> حذف الدورة</button></form>
@endif
@endsection
