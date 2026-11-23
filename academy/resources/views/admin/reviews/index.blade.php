@extends('layouts.admin')
@section('title', 'المراجعات')
@section('content')
<div class="page-head"><h1>مراجعات الطلاب</h1></div>
<nav class="tabs"><a href="{{ route('admin.reviews.index') }}" class="{{ !request('status') ? 'is-active' : '' }}">الكل</a><a href="{{ route('admin.reviews.index', ['status' => 'pending']) }}" class="{{ request('status') === 'pending' ? 'is-active' : '' }}">بانتظار الاعتماد</a><a href="{{ route('admin.reviews.index', ['status' => 'approved']) }}" class="{{ request('status') === 'approved' ? 'is-active' : '' }}">معتمدة</a></nav>
<div class="table-wrap"><table class="table">
    <thead><tr><th>الطالب</th><th>الدورة</th><th>التقييم</th><th>التعليق</th><th>الحالة</th><th></th></tr></thead>
    <tbody>
    @forelse ($reviews as $r)
        <tr><td>{{ $r->user?->name }}</td><td class="small">{{ $r->course?->tr('title') }}</td><td>{{ str_repeat('★', $r->rating) }}</td><td class="small" dir="auto" style="max-width:340px">{{ $r->body }}</td>
            <td>@if ($r->is_approved)<span class="badge badge--green">معتمدة</span>@else<span class="badge badge--amber">بالانتظار</span>@endif</td>
            <td class="row"><form method="POST" action="{{ route('admin.reviews.approve', $r) }}">@csrf<button class="btn btn--sm {{ $r->is_approved ? 'btn--ghost' : 'btn--primary' }}" type="submit">{{ $r->is_approved ? 'إخفاء' : 'اعتماد' }}</button></form>
                <form method="POST" action="{{ route('admin.reviews.destroy', $r) }}" data-confirm="حذف المراجعة؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm" type="submit">حذف</button></form></td></tr>
    @empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد مراجعات.</td></tr>@endforelse
    </tbody>
</table></div>
{{ $reviews->links() }}
@endsection
