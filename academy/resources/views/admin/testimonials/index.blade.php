@extends('layouts.admin')
@section('title', 'آراء الطلاب')
@section('content')
<div class="page-head"><h1>آراء الطلاب (الرئيسية)</h1><a class="btn btn--primary" href="{{ route('admin.testimonials.create') }}"><x-icon name="plus" size="18"/> إضافة</a></div>
<p class="muted small">تظهر فقط الآراء المعتمدة. أضف آراء حقيقية بموافقة أصحابها.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>الاسم</th><th>الرأي</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $t)
    <tr><td>{{ $t->name }} @if ($t->is_demo)<span class="badge demo-tag">تجريبي</span>@endif</td><td class="small" dir="auto">{{ \Illuminate\Support\Str::limit($t->tr('body'), 90) }}</td>
        <td>@if ($t->is_approved)<span class="badge badge--green">معتمد</span>@else<span class="badge badge--gray">مخفي</span>@endif</td>
        <td class="row"><a class="btn btn--ghost btn--sm" href="{{ route('admin.testimonials.edit', $t) }}">تعديل</a><form method="POST" action="{{ route('admin.testimonials.destroy', $t) }}" data-confirm="حذف؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></td></tr>
@empty<tr><td colspan="4" class="muted" style="text-align:center;padding:24px">لا شيء بعد.</td></tr>@endforelse
</tbody></table></div>
{{ $items->links() }}
@endsection
