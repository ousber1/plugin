@extends('layouts.admin')
@section('title', 'مراكز التكوين')
@section('content')
<div class="page-head"><h1>مراكز التكوين</h1>
    <div class="row">
        @if ($items->where('is_active', true)->isNotEmpty())<a class="btn btn--ghost" href="{{ route('centers.index') }}" target="_blank"><x-icon name="eye" size="18"/> عرض الصفحة</a>@endif
        <a class="btn btn--primary" href="{{ route('admin.centers.create') }}"><x-icon name="plus" size="18"/> مركز جديد</a>
    </div>
</div>
<p class="muted small">أضف مراكز الأكاديمية في مختلف المدن. تظهر صفحة «مراكزنا» في القائمة تلقائيًا عند وجود مركز مفعّل، ويمكن للطالب اختيار مركزه الأقرب واستلام شهادته منه.</p>
@if ($items->isNotEmpty())
    <div class="kpis" style="margin-bottom:16px">
        <div class="kpi"><strong>{{ $items->where('is_active', true)->count() }}</strong><span>مركز مفعّل</span></div>
        <div class="kpi"><strong>{{ $items->map(fn ($c) => $c->tr('city'))->unique()->count() }}</strong><span>مدينة</span></div>
        <div class="kpi"><strong>{{ $items->sum('students_count') }}</strong><span>طالب مرتبط بمركز</span></div>
        <div class="kpi"><strong>{{ $items->sum('certificate_requests_count') }}</strong><span>شهادة للاستلام من المراكز</span></div>
    </div>
@endif
<div class="table-wrap"><table class="table"><thead><tr><th>المركز</th><th>المدينة</th><th>الهاتف / واتساب</th><th>دورات</th><th>طلاب</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $c)
    <tr>
        <td><strong>{{ $c->tr('name') }}</strong><br><small class="muted">{{ $c->tr('address') }}</small></td>
        <td>{{ $c->tr('city') }}</td>
        <td class="ltr small">{{ $c->phone }}@if ($c->whatsapp)<br>wa: {{ $c->whatsapp }}@endif</td>
        <td>{{ $c->courses_count }}</td>
        <td><a href="{{ route('admin.users.index', ['center' => $c->id]) }}">{{ $c->students_count }}</a></td>
        <td>@if ($c->is_active)<span class="badge badge--green">مفعّل</span>@else<span class="badge badge--gray">مخفي</span>@endif</td>
        <td class="row">
            @if ($c->is_active)<a class="btn btn--ghost btn--sm" href="{{ route('centers.show', $c) }}" target="_blank">عرض</a>@endif
            <a class="btn btn--ghost btn--sm" href="{{ route('admin.centers.edit', $c) }}">تعديل</a>
            <form method="POST" action="{{ route('admin.centers.destroy', $c) }}" data-confirm="حذف المركز؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form>
        </td>
    </tr>
@empty<tr><td colspan="7" class="muted" style="text-align:center;padding:24px">لا توجد مراكز بعد. أضف أول مركز (مثلًا: الدار البيضاء — المعاريف).</td></tr>@endforelse
</tbody></table></div>
@endsection
