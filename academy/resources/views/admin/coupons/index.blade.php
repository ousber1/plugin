@extends('layouts.admin')
@section('title', 'رموز الخصم')
@section('content')
<div class="page-head"><h1>رموز الخصم</h1><a class="btn btn--primary" href="{{ route('admin.coupons.create') }}"><x-icon name="plus" size="18"/> رمز جديد</a></div>
<p class="muted small">يكتب الزبون الرمز في السلة. الخصم الثابت بالدرهم ويُحوَّل تلقائيًا للدولار.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>الرمز</th><th>الخصم</th><th>الاستعمال</th><th>ينتهي</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $c)
    <tr><td class="ltr"><strong>{{ $c->code }}</strong></td><td class="ltr">{{ $c->label() }}</td><td>{{ $c->used }}{{ $c->max_uses ? ' / '.$c->max_uses : '' }}</td>
        <td class="small">{{ $c->expires_at?->format('Y-m-d') ?? '—' }}</td>
        <td>@if ($c->usable())<span class="badge badge--green">صالح</span>@else<span class="badge badge--gray">غير صالح</span>@endif</td>
        <td class="row"><a class="btn btn--ghost btn--sm" href="{{ route('admin.coupons.edit', $c) }}">تعديل</a><form method="POST" action="{{ route('admin.coupons.destroy', $c) }}" data-confirm="حذف الرمز؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></td></tr>
@empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد رموز خصم.</td></tr>@endforelse
</tbody></table></div>
@endsection
