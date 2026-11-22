@extends('layouts.admin')
@section('title', 'سجل التحقق')
@section('content')
<div class="page-head"><h1>سجل الشهادات (التحقق العام)</h1><a class="btn btn--primary" href="{{ route('admin.certificates.create') }}"><x-icon name="plus" size="18"/> تسجيل شهادة</a></div>
<form class="filters" method="GET"><div class="field" style="flex:2"><label for="q">بحث</label><input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="الرمز، الاسم أو الدورة"></div>
    <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="">الكل</option><option value="valid" @selected(request('status') === 'valid')>صالحة</option><option value="revoked" @selected(request('status') === 'revoked')>ملغاة</option></select></div><button class="btn btn--primary">بحث</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>الرمز</th><th>الاسم</th><th>الدورة</th><th>الإصدار</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $c)
    <tr><td class="ltr"><strong>{{ $c->code }}</strong></td><td dir="auto">{{ $c->holder_name }}</td><td class="small" dir="auto">{{ $c->course_title }}</td><td class="small">{{ $c->issued_on->format('Y-m-d') }}</td>
        <td>@if ($c->isRevoked())<span class="badge badge--red">ملغاة</span>@else<span class="badge badge--green">صالحة</span>@endif</td><td><a class="btn btn--ghost btn--sm" href="{{ route('admin.certificates.show', $c) }}">عرض</a></td></tr>
@empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد شهادات مسجلة.</td></tr>@endforelse
</tbody></table></div>
{{ $items->links() }}
@endsection
