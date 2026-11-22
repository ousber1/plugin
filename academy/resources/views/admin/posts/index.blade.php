@extends('layouts.admin')
@section('title', 'المدونة')
@section('content')
<div class="page-head"><h1>المدونة والأخبار</h1><div class="row"><a class="btn btn--outline" href="{{ route('admin.categories.index', ['type' => 'post']) }}">التصنيفات</a><a class="btn btn--primary" href="{{ route('admin.posts.create') }}"><x-icon name="plus" size="18"/> مقال جديد</a></div></div>
<p class="muted small">المقالات في تصنيف «news» تظهر في «أخبارنا».</p>
<form class="filters" method="GET"><div class="field" style="flex:2"><label for="q">بحث</label><input class="input" id="q" name="q" value="{{ request('q') }}" dir="auto"></div>
    <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="">الكل</option><option value="published" @selected(request('status') === 'published')>منشور</option><option value="draft" @selected(request('status') === 'draft')>مسودة</option></select></div>
    <button class="btn btn--primary">تصفية</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>العنوان</th><th>التصنيف</th><th>الكاتب</th><th>النشر</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($posts as $p)
    <tr><td dir="auto"><strong>{{ $p->tr('title') }}</strong> @if ($p->is_demo)<span class="badge demo-tag">تجريبي</span>@endif</td><td>{{ $p->category?->tr('name') ?? '—' }}</td><td class="small">{{ $p->author?->name }}</td><td class="small">{{ $p->published_at?->format('Y-m-d') ?? '—' }}</td><td><x-admin.status :status="$p->status"/></td>
        <td class="row"><a class="btn btn--ghost btn--sm" href="{{ route('admin.posts.edit', $p) }}">تعديل</a><a class="btn btn--ghost btn--sm" href="{{ route('blog.show', $p) }}" target="_blank" aria-label="معاينة"><x-icon name="eye" size="16"/></a></td></tr>
@empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد مقالات.</td></tr>@endforelse
</tbody></table></div>
{{ $posts->links() }}
@endsection
