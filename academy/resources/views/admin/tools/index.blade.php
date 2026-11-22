@extends('layouts.admin')
@section('title', 'الأدوات')
@section('content')
<div class="page-head"><h1>الأدوات واقتراحات الزوار</h1></div>
<form class="card card--pad" method="POST" action="{{ route('admin.tools.update') }}" style="margin-bottom:24px">
    @csrf @method('PUT')
    <h2 class="card__title">الأدوات المتاحة</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>الأداة</th><th>مفعّلة</th><th>الترتيب</th><th>شارة</th></tr></thead><tbody>
    @foreach ($tools as $t)
        <tr><td><strong>{{ $t->tr('name') }}</strong><br><span class="small muted ltr">/tools/{{ $t->key }}</span></td>
            <td><input type="checkbox" name="tools[{{ $t->id }}][is_enabled]" value="1" @checked($t->is_enabled) aria-label="تفعيل {{ $t->tr('name') }}"></td>
            <td><input class="input" type="number" min="0" name="tools[{{ $t->id }}][sort_order]" value="{{ $t->sort_order }}" style="max-width:90px" aria-label="الترتيب"></td>
            <td><input class="input" name="tools[{{ $t->id }}][badge]" value="{{ $t->badge }}" style="max-width:140px" aria-label="الشارة"></td></tr>
    @endforeach
    </tbody></table></div>
    <button class="btn btn--primary" type="submit" style="margin-top:12px">حفظ</button>
</form>
<h2>اقتراحات الأدوات</h2>
<nav class="tabs">@foreach (['' => 'الكل', 'pending' => 'جديدة', 'approved' => 'مقبولة', 'done' => 'منفّذة', 'rejected' => 'مرفوضة'] as $k => $l)<a class="{{ request('status', '') === $k ? 'is-active' : '' }}" href="{{ route('admin.tools.index', array_filter(['status' => $k])) }}">{{ $l }}</a>@endforeach</nav>
@forelse ($suggestions as $s)
    <div class="card card--pad" style="margin-bottom:12px">
        <p dir="auto" style="white-space:pre-line">{{ $s->idea }}</p>
        <p class="small muted">{{ $s->name ?: 'بدون اسم' }} @if ($s->email)— <span class="ltr">{{ $s->email }}</span>@endif — {{ $s->created_at->diffForHumans() }}</p>
        <form class="row" method="POST" action="{{ route('admin.tool-suggestions.update', $s) }}">@csrf @method('PUT')
            <select class="select" name="status" style="max-width:160px;min-height:36px" aria-label="الحالة">@foreach (['pending' => 'جديدة', 'approved' => 'مقبولة', 'done' => 'منفّذة', 'rejected' => 'مرفوضة'] as $k => $l)<option value="{{ $k }}" @selected($s->status === $k)>{{ $l }}</option>@endforeach</select>
            <input class="input" name="admin_note" value="{{ $s->admin_note }}" placeholder="ملاحظة داخلية" aria-label="ملاحظة داخلية" style="flex:1;min-height:36px">
            <button class="btn btn--primary btn--sm">حفظ</button></form>
        <form method="POST" action="{{ route('admin.tool-suggestions.destroy', $s) }}" data-confirm="حذف الاقتراح؟" style="margin-top:6px">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form>
    </div>
@empty<p class="muted">لا توجد اقتراحات.</p>@endforelse
{{ $suggestions->links() }}
@endsection
