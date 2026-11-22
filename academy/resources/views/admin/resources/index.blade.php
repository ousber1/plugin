@extends('layouts.admin')
@section('title', 'المكتبة')
@section('content')
<div class="page-head"><h1>موارد المكتبة</h1><div class="row"><a class="btn btn--outline" href="{{ route('admin.categories.index', ['type' => 'resource']) }}">التصنيفات</a><a class="btn btn--primary" href="{{ route('admin.resources.create') }}"><x-icon name="plus" size="18"/> مورد جديد</a></div></div>
<form class="filters" method="GET"><div class="field" style="flex:2"><label for="q">بحث</label><input class="input" id="q" name="q" value="{{ request('q') }}"></div><button class="btn btn--primary">بحث</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>العنوان</th><th>التصنيف</th><th>الوصول</th><th>التحميلات</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $r)
    <tr><td dir="auto">{{ $r->tr('title') }} @if ($r->is_demo)<span class="badge demo-tag">تجريبي</span>@endif<br><span class="small muted">{{ $r->original_name }}</span></td><td>{{ $r->category?->tr('name') ?? '—' }}</td>
        <td>@if ($r->isRestricted())<span class="badge badge--amber">طلاب: {{ $r->course?->tr('title') }}</span>@else<span class="badge badge--green">عام</span>@endif</td><td>{{ $r->downloads }}</td><td><x-admin.status :status="$r->status"/></td>
        <td class="row"><a class="btn btn--ghost btn--sm" href="{{ route('admin.resources.edit', $r) }}">تعديل</a><form method="POST" action="{{ route('admin.resources.destroy', $r) }}" data-confirm="حذف المورد وملفه؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></td></tr>
@empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد موارد.</td></tr>@endforelse
</tbody></table></div>
{{ $items->links() }}
@endsection
