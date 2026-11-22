@extends('layouts.admin')
@section('title', 'المستخدمون')
@section('content')
<div class="page-head"><h1>المستخدمون</h1></div>
<form class="filters" method="GET">
    <div class="field" style="flex:2"><label for="q">بحث</label><input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="الاسم، البريد أو الهاتف"></div>
    <div class="field"><label for="role">الدور</label><select class="select" id="role" name="role"><option value="">الكل</option>@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}" @selected(request('role') === $r->value)>{{ $r->label() }}</option>@endforeach</select></div>
    <button class="btn btn--primary" type="submit">بحث</button>
</form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>الدورات</th><th>الحالة</th><th>التسجيل</th><th></th></tr></thead>
    <tbody>
    @foreach ($users as $u)
        <tr>
            <td><strong>{{ $u->name }}</strong> @if ($u->is_demo)<span class="badge demo-tag">تجريبي</span>@endif</td>
            <td class="ltr small">{{ $u->email }} @unless ($u->hasVerifiedEmail())<span class="badge badge--amber">غير مؤكد</span>@endunless</td>
            <td><span class="badge">{{ $u->role->label() }}</span></td>
            <td>{{ $u->enrollments_count }}</td>
            <td>@if ($u->is_active)<span class="badge badge--green">نشط</span>@else<span class="badge badge--red">معطّل</span>@endif</td>
            <td class="small">{{ $u->created_at->format('Y-m-d') }}</td>
            <td><a class="btn btn--ghost btn--sm" href="{{ route('admin.users.edit', $u) }}">إدارة</a></td>
        </tr>
    @endforeach
    </tbody>
</table></div>
{{ $users->links() }}
@endsection
