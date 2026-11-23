@extends('layouts.admin')
@section('title', 'الصفحات')
@section('content')
<div class="page-head"><h1>الصفحات الثابتة</h1><a class="btn btn--outline" href="{{ route('admin.builder.edit', 'home') }}"><x-icon name="sparkles" size="18"/> تصميم الصفحة الرئيسية</a><a class="btn btn--primary" href="{{ route('admin.pages.create') }}"><x-icon name="plus" size="18"/> صفحة جديدة</a></div>
<div class="table-wrap"><table class="table"><thead><tr><th>العنوان</th><th>الرابط</th><th>في التذييل</th><th>الحالة</th><th></th></tr></thead><tbody>
@foreach ($pages as $p)
    <tr><td>{{ $p->tr('title') }}</td><td class="ltr small">/p/{{ $p->slug }}</td><td>{{ $p->show_in_footer ? '✓' : '' }}</td><td><x-admin.status :status="$p->status"/></td>
        <td class="row"><a class="btn btn--outline btn--sm" href="{{ route('admin.builder.edit', $p->id) }}"><x-icon name="sparkles" size="14"/> تصميم بالمحرّر</a>@if ($p->use_builder)<span class="badge badge--indigo">بالمحرّر</span>@endif <a class="btn btn--ghost btn--sm" href="{{ route('admin.pages.edit', $p) }}">تعديل</a>
            @unless (in_array($p->slug, ['about', 'privacy']))<form method="POST" action="{{ route('admin.pages.destroy', $p) }}" data-confirm="حذف الصفحة؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form>@endunless</td></tr>
@endforeach
</tbody></table></div>
@endsection
