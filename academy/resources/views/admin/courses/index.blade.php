@extends('layouts.admin')
@section('title', 'الدورات')
@section('content')
<div class="page-head"><h1>{{ auth()->user()->isAdmin() ? 'الدورات' : 'دوراتي' }}</h1>
    @if (auth()->user()->isAdmin())<a class="btn btn--primary" href="{{ route('admin.courses.create') }}"><x-icon name="plus" size="18"/> دورة جديدة</a>@endif</div>
<form class="filters" method="GET">
    <div class="field" style="flex:2"><label for="q">بحث</label><input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="عنوان الدورة أو الرابط" dir="auto"></div>
    <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="">الكل</option><option value="published" @selected(request('status') === 'published')>منشورة</option><option value="draft" @selected(request('status') === 'draft')>مسودة</option></select></div>
    <div class="field"><label for="category">التصنيف</label><select class="select" id="category" name="category"><option value="">الكل</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->tr('name') }}</option>@endforeach</select></div>
    <button class="btn btn--primary" type="submit">تصفية</button>
</form>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>الدورة</th><th>التصنيف</th><th>المدرّس</th><th>الدروس</th><th>الطلاب</th><th>الحالة</th><th><span class="sr-only">إجراءات</span></th></tr></thead>
        <tbody>
        @forelse ($courses as $course)
            <tr>
                <td><strong dir="auto">{{ $course->tr('title') }}</strong> @if ($course->is_demo)<span class="badge demo-tag">محتوى تجريبي</span>@endif @if ($course->is_featured)<span class="badge badge--amber">مميزة</span>@endif<br><span class="muted small ltr">/{{ $course->slug }}</span></td>
                <td>{{ $course->category?->tr('name') ?? '—' }}</td>
                <td>{{ $course->instructor?->name ?? '—' }}</td>
                <td>{{ $course->lessons_count }}</td>
                <td><a href="{{ route('admin.courses.enrollments', $course) }}">{{ $course->enrollments_count }}</a></td>
                <td><x-admin.status :status="$course->status"/></td>
                <td class="row" style="flex-wrap:nowrap">
                    <a class="btn btn--outline btn--sm" href="{{ route('admin.courses.curriculum', $course) }}">المحتوى</a>
                    <a class="btn btn--ghost btn--sm" href="{{ route('admin.courses.edit', $course) }}">تعديل</a>
                    <a class="btn btn--ghost btn--sm" href="{{ route('courses.show', $course) }}" target="_blank" aria-label="معاينة"><x-icon name="eye" size="16"/></a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted" style="text-align:center;padding:30px">لا توجد دورات.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $courses->links() }}
@endsection
