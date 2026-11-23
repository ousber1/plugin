@extends('layouts.admin')
@section('title', 'الشركاء')
@section('content')
<div class="page-head"><h1>الشركاء والاعتمادات</h1><a class="btn btn--primary" href="{{ route('admin.partners.create') }}"><x-icon name="plus" size="18"/> إضافة شريك</a></div>
<p class="muted small">تظهر الشعارات في الرئيسية فقط عند تفعيلها. أضف فقط الشركاء الذين وافقوا على استعمال شعاراتهم. نص الاعتماد يُضبط من إعدادات الصفحة الرئيسية.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>الشعار</th><th>الاسم</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $p)
    <tr><td>@if ($p->logo_path)<img src="{{ \App\Support\Media::url($p->logo_path) }}" alt="" style="max-height:36px">@endif</td><td>{{ $p->name }}</td><td>@if ($p->is_active)<span class="badge badge--green">مفعّل</span>@else<span class="badge badge--gray">مخفي</span>@endif</td>
        <td class="row"><a class="btn btn--ghost btn--sm" href="{{ route('admin.partners.edit', $p) }}">تعديل</a><form method="POST" action="{{ route('admin.partners.destroy', $p) }}" data-confirm="حذف؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></td></tr>
@empty<tr><td colspan="4" class="muted" style="text-align:center;padding:24px">لا يوجد شركاء.</td></tr>@endforelse
</tbody></table></div>
@endsection
